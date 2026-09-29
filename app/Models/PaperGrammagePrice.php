<?php

namespace App\Models;

use Database\Factories\PaperGrammagePriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['paper_grammage_id', 'paper_supplier_id', 'price_per_ton_egp', 'price_as_of'])]
class PaperGrammagePrice extends Model
{
    /** @use HasFactory<PaperGrammagePriceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_per_ton_egp' => 'decimal:2',
            'price_as_of' => 'datetime',
        ];
    }

    /** @return BelongsTo<PaperGrammage, $this> */
    public function grammage(): BelongsTo
    {
        return $this->belongsTo(PaperGrammage::class, 'paper_grammage_id');
    }

    /** @return BelongsTo<PaperSupplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(PaperSupplier::class, 'paper_supplier_id');
    }
}
