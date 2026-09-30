<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_id
 * @property string $label
 * @property int $paper_grammage_id
 * @property int|null $paper_grammage_price_id
 * @property string $sheet_width_cm
 * @property string $sheet_height_cm
 * @property int $sheets_count
 * @property string $weight_kg
 * @property string $cost_egp
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'job_id', 'label', 'paper_grammage_id', 'paper_grammage_price_id', 'sheet_width_cm',
    'sheet_height_cm', 'sheets_count', 'weight_kg', 'cost_egp', 'sort_order',
])]
class JobPaperItem extends Model
{
    protected function casts(): array
    {
        return [
            'sheet_width_cm' => 'decimal:2',
            'sheet_height_cm' => 'decimal:2',
            'sheets_count' => 'integer',
            'weight_kg' => 'decimal:3',
            'cost_egp' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<PaperGrammage, $this> */
    public function paperGrammage(): BelongsTo
    {
        return $this->belongsTo(PaperGrammage::class);
    }

    /** @return BelongsTo<PaperGrammagePrice, $this> */
    public function paperGrammagePrice(): BelongsTo
    {
        return $this->belongsTo(PaperGrammagePrice::class);
    }
}
