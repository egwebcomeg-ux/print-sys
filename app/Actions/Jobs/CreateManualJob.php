<?php

namespace App\Actions\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\Lamination;
use App\Models\Job;
use App\Models\PaperGrammage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists a manual paper job (ManualJobCostingCalculator's ManualJobQuote).
 * Paper weight/cost are recomputed from the DB prices with the same formula
 * as the component's computeLineItem(), so the stored numbers are the
 * server's, not the browser's.
 */
class CreateManualJob
{
    public function __construct(private readonly LinkLeadToJob $linkLead) {}

    /** @param array<string, mixed> $quote validated StoreManualJobRequest data */
    public function handle(array $quote): Job
    {
        $items = $this->priceLineItems($quote['lineItems']);

        // Free-form extra costs; zero lines are dropped, unnamed ones get a generic label.
        $costLines = collect($quote['costLines'])
            ->map(fn ($line) => [
                'label' => trim((string) ($line['label'] ?? '')) ?: 'مصاريف',
                'amount_egp' => round((float) $line['amountEgp'], 2),
            ])
            ->filter(fn ($line) => $line['amount_egp'] > 0)
            ->values();

        $paperCost = round($items->sum('cost_egp'), 2);
        $baseCost = round($paperCost + $costLines->sum('amount_egp'), 2);
        $margin = (float) $quote['marginPercent'];
        $finalPrice = round($baseCost * (1 + $margin / 100), 2);

        if (abs($finalPrice - (float) $quote['finalPriceEgp']) > 1) {
            throw ValidationException::withMessages([
                'finalPriceEgp' => 'أسعار الورق اتغيرت من ساعة ما فتحت الصفحة — اعمل تحديث للصفحة وراجع السعر.',
            ]);
        }

        return DB::transaction(function () use ($quote, $items, $costLines, $baseCost, $margin, $finalPrice) {
            $job = Job::query()->create([
                'customer_id' => $quote['customer_id'],
                'job_type' => JobType::Manual,
                'title' => $quote['title'],
                'quantity' => $quote['producedQuantity'] ?? null, // the calculator's piece count = quoted quantity
                'lamination' => Lamination::None,
                'is_using_existing_die' => false,
                'base_cost_egp' => $baseCost,
                'margin_percent' => $margin,
                'final_price_egp' => $finalPrice,
                'quote_snapshot' => $quote,
                'status' => JobStatus::Draft,
            ]);

            $job->paperItems()->createMany($items->all());

            if ($items->sum('cost_egp') > 0) {
                $job->costLines()->create(['label' => 'ورق', 'amount_egp' => round($items->sum('cost_egp'), 2)]);
            }
            $job->costLines()->createMany($costLines->all());

            if (! empty($quote['lead_id'])) {
                $this->linkLead->handle((int) $quote['lead_id'], $job);
            }

            return $job;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @return Collection<int, array<string, mixed>>
     */
    private function priceLineItems(array $lineItems)
    {
        $grammages = PaperGrammage::query()
            ->with('prices')
            ->whereIn('id', array_column($lineItems, 'grammageId'))
            ->get()
            ->keyBy('id');

        return collect($lineItems)->values()->map(function (array $item, int $index) use ($grammages) {
            $grammage = $grammages[(int) $item['grammageId']];

            if ($grammage->paper_type_id !== (int) $item['paperTypeId']) {
                throw ValidationException::withMessages(["lineItems.{$index}.grammageId" => 'الجرام ده مش تبع نوع الورق المختار']);
            }

            // Same fallback as the component: picked supplier, else the cheapest.
            $price = ! empty($item['supplierPriceId'])
                ? $grammage->prices->firstWhere('id', (int) $item['supplierPriceId'])
                : $grammage->prices->first();

            if (! $price) {
                throw ValidationException::withMessages(["lineItems.{$index}.supplierPriceId" => 'مفيش سعر مسجل للجرام ده']);
            }

            $weightKg = self::weightKg((float) $item['sheetWidthCm'], (float) $item['sheetHeightCm'], $grammage->gsm, (int) $item['sheetsCount']);

            return [
                'label' => $item['label'],
                'paper_grammage_id' => $grammage->id,
                'paper_grammage_price_id' => $price->id,
                'sheet_width_cm' => $item['sheetWidthCm'],
                'sheet_height_cm' => $item['sheetHeightCm'],
                'sheets_count' => $item['sheetsCount'],
                'weight_kg' => round($weightKg, 3),
                'cost_egp' => round($weightKg * ((float) $price->price_per_ton_egp / 1000), 2),
                'sort_order' => $index,
            ];
        });
    }

    /** Mirrors computeLineItem() in ManualJobCostingCalculator.tsx. */
    public static function weightKg(float $widthCm, float $heightCm, int $gsm, int $sheets): float
    {
        $areaM2 = ($widthCm / 100) * ($heightCm / 100);

        return ($areaM2 * $gsm / 1000) * $sheets;
    }
}
