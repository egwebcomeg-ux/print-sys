<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
