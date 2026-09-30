<?php

namespace App\Services\Inventory;

use App\Enums\JobType;
use App\Enums\StockMovementType;
use App\Models\Job;
use App\Models\JobPaperItem;
use App\Models\PaperStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Paper stock bookkeeping. Every change goes through move() so the movement
 * log always explains the current balance.
 *
 * Only paper the factory tracks (has a paper_stocks row for) is deducted;
 * untracked paper is assumed to be bought per job.
 */
class PaperInventory
{
    /**
     * Raw sheets a job needs, grouped by paper + sheet size.
     *
     * @return array<string, array{grammageId: int, widthCm: float, heightCm: float, sheets: int}>
     */
    public static function requirements(Job $job): array
    {
        $needs = [];
        $add = function (?int $grammageId, mixed $w, mixed $h, ?int $sheets) use (&$needs): void {
            if (! $grammageId || ! is_numeric($w) || ! is_numeric($h) || ! $sheets || $sheets < 1) {
                return;
            }
            [$w, $h] = self::normalize((float) $w, (float) $h);
            $key = "{$grammageId}:{$w}:{$h}";
            $needs[$key] ??= ['grammageId' => $grammageId, 'widthCm' => $w, 'heightCm' => $h, 'sheets' => 0];
            $needs[$key]['sheets'] += $sheets;
        };

        if ($job->job_type === JobType::Box) {
            $plan = $job->quote_snapshot['pricing'] ?? [];
            $add($job->paper_grammage_id, $plan['sheetWidthCm'] ?? null, $plan['sheetHeightCm'] ?? null, $job->raw_sheets_needed);
        } else {
            $job->paperItems->each(fn (JobPaperItem $item) => $add($item->paper_grammage_id, $item->sheet_width_cm, $item->sheet_height_cm, $item->sheets_count));
        }

        return $needs;
    }

    /**
     * Short side first, so 100×70 and 70×100 are the same stock item.
     *
     * @return array{0: float, 1: float}
     */
    public static function normalize(float $w, float $h): array
    {
        return [round(min($w, $h), 2), round(max($w, $h), 2)];
    }

    public static function find(int $grammageId, float $w, float $h): ?PaperStock
    {
        [$w, $h] = self::normalize($w, $h);

        return PaperStock::query()
            ->where('paper_grammage_id', $grammageId)
            ->where('sheet_width_cm', $w)
            ->where('sheet_height_cm', $h)
            ->first();
    }

    /**
     * Tracked items the job needs more of than is on hand.
     *
     * @return list<array{stock: PaperStock, needed: int, available: int}>
     */
    public static function shortages(Job $job): array
    {
        $short = [];
        foreach (self::requirements($job) as $need) {
            $stock = self::find($need['grammageId'], $need['widthCm'], $need['heightCm']);
            if ($stock && $stock->quantity_sheets < $need['sheets']) {
                $short[] = ['stock' => $stock, 'needed' => $need['sheets'], 'available' => $stock->quantity_sheets];
            }
        }

        return $short;
    }

    /**
     * Deduct the job's paper from tracked stock (on approval). Idempotent.
     *
     * @return list<PaperStock> stock items that are now at/below their reorder level
     */
    public static function consumeFor(Job $job, ?User $actor): array
    {
        if (StockMovement::query()->where('job_id', $job->id)->where('type', StockMovementType::Consumption)->exists()) {
            return [];
        }

        $low = [];
        foreach (self::requirements($job) as $need) {
            $stock = self::find($need['grammageId'], $need['widthCm'], $need['heightCm']);
            if ($stock === null) {
                continue;
            }
            $stock = self::move($stock, StockMovementType::Consumption, -$need['sheets'], $actor, "صرف للشغلانة #{$job->id}", $job->id);
            if ($stock->isLow()) {
                $low[] = $stock;
            }
        }

        return $low;
    }

    /** Apply a signed delta and log it, under a row lock. */
    public static function move(PaperStock $stock, StockMovementType $type, int $delta, ?User $actor, ?string $note = null, ?int $jobId = null, ?int $supplierId = null): PaperStock
    {
        return DB::transaction(function () use ($stock, $type, $delta, $actor, $note, $jobId, $supplierId) {
            $locked = PaperStock::query()->lockForUpdate()->findOrFail($stock->id);
            $locked->quantity_sheets += $delta;
            $locked->save();

            $locked->movements()->create([
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $locked->quantity_sheets,
                'job_id' => $jobId,
                'paper_supplier_id' => $supplierId,
                'user_id' => $actor?->id,
                'note' => $note,
            ]);

            return $locked;
        });
    }
}
