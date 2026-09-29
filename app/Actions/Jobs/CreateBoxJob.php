<?php

namespace App\Actions\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Job;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Persists a confirmed box quote. `$quote` is the validated BoxQuote payload
 * (what the user saw); `$pricing` is BoxPricer's server-side recomputation
 * (what gets stored and invoiced).
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

    /** What the snapshot keeps: the inputs that describe the quote, not the money (that's in columns). */
    private const SNAPSHOT_KEYS = [
        'boxType', 'shape', 'dimensions', 'quantity', 'paperTypeName', 'gsm', 'supplierName', 'pricePerTonEgp',
        'printColors', 'lamination', 'isUsingExistingDie', 'dieId', 'flatWidthMm', 'flatHeightMm', 'interlockEnabled',
    ];

    public function __construct(private readonly LinkLeadToJob $linkLead) {}

    /**
     * @param  array<string, mixed>  $quote  validated StoreBoxJobRequest data
     * @param  array<string, mixed>  $pricing  StoreBoxJobRequest::pricing()
     */
    public function handle(array $quote, array $pricing): Job
    {
        return DB::transaction(function () use ($quote, $pricing) {
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
                'paper_grammage_price_id' => $quote['supplierPriceId'],
                'print_colors' => $quote['printColors'],
                'lamination' => $quote['lamination'],
                'is_using_existing_die' => $usingDie,
                'die_id' => $usingDie ? $quote['dieId'] : null,
                'raw_sheets_needed' => $pricing['rawSheetsNeeded'],
                'ups_per_raw_sheet' => $pricing['upsPerRawSheet'],
                'interlocked' => $pricing['interlocked'],
                'base_cost_egp' => $pricing['baseCostEgp'],
                'margin_percent' => $pricing['marginPercent'],
                'final_price_egp' => $pricing['totalPriceEgp'],
                'quote_snapshot' => Arr::only($quote, self::SNAPSHOT_KEYS) + ['pricing' => $pricing],
                'status' => JobStatus::Draft,
            ]);

            foreach (self::COST_LABELS as $key => $label) {
                $amount = $pricing['costBreakdown'][$key];
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
}
