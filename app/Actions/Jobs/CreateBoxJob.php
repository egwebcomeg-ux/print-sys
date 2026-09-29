<?php

namespace App\Actions\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Job;
use App\Models\PaperGrammagePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists a confirmed box quote (QuickBoxPricingCalculator's BoxQuote).
 */
class CreateBoxJob
{
    /** Cost-breakdown key → Arabic label on the job's cost lines. */
    private const COST_LABELS = [
        'paperCost' => 'ورق',
        'platesCost' => 'زنكات',
        'pressRunCost' => 'طباعة',
        'laminationCost' => 'سلوفان',
        'dieToolingCost' => 'اسطمبة جديدة',
        'dieCuttingRunCost' => 'تكسير',
        'gluingCost' => 'لصق وتطبيق',
    ];

    public function __construct(private readonly LinkLeadToJob $linkLead) {}

    /** @param array<string, mixed> $quote validated StoreBoxJobRequest data */
    public function handle(array $quote): Job
    {
        $this->warnOnStalePrice($quote);

        return DB::transaction(function () use ($quote) {
            $usingDie = (bool) $quote['isUsingExistingDie'];

            $job = Job::query()->create([
                'customer_id' => $quote['customer_id'],
                'job_type' => JobType::Box,
                'title' => $quote['title'] ?? null,
                'box_type' => $quote['boxType'],
                'box_shape' => $quote['shape'],
                'length_cm' => $quote['dimensions']['lengthCm'],
                'width_cm' => $quote['dimensions']['widthCm'],
                'depth_cm' => $quote['dimensions']['depthCm'],
                'quantity' => $quote['quantity'],
                'paper_grammage_id' => $quote['grammageId'],
                'paper_grammage_price_id' => $quote['supplierPriceId'] ?? null,
                'print_colors' => $quote['printColors'],
                'lamination' => $quote['lamination'],
                'is_using_existing_die' => $usingDie,
                'die_id' => $usingDie ? $quote['dieId'] : null,
                'raw_sheets_needed' => $quote['rawSheetsNeeded'],
                'ups_per_raw_sheet' => $quote['upsPerRawSheet'],
                'interlocked' => $quote['interlocked'],
                'base_cost_egp' => $quote['baseCostEgp'],
                'margin_percent' => $quote['marginPercent'],
                'final_price_egp' => $quote['totalPriceEgp'],
                'quote_snapshot' => $quote,
                'status' => JobStatus::Draft,
            ]);

            foreach (self::COST_LABELS as $key => $label) {
                $amount = round((float) ($quote['costBreakdown'][$key] ?? 0), 2);
                if ($amount > 0) {
                    $job->costLines()->create(['label' => $label, 'amount_egp' => $amount]);
                }
            }

            if (! empty($quote['lead_id'])) {
                $this->linkLead->handle((int) $quote['lead_id'], $job);
            }

            return $job;
        });
    }

    /**
     * The calculator prices with whatever it loaded; if the DB price changed
     * meanwhile, keep the quote but leave a trace for review.
     *
     * @param  array<string, mixed>  $quote
     */
    private function warnOnStalePrice(array $quote): void
    {
        if (empty($quote['supplierPriceId']) || ! isset($quote['pricePerTonEgp'])) {
            return;
        }

        $current = (float) PaperGrammagePrice::query()->whereKey($quote['supplierPriceId'])->value('price_per_ton_egp');
        if (abs($current - (float) $quote['pricePerTonEgp']) > 0.01) {
            Log::warning('Box quote priced with a stale paper price', [
                'supplier_price_id' => $quote['supplierPriceId'],
                'quoted' => $quote['pricePerTonEgp'],
                'current' => $current,
            ]);
        }
    }
}
