<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaperGrammagePriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $paper_grammage_id
 * @property int $paper_supplier_id
 * @property string $price_per_ton_egp
 * @property CarbonImmutable $price_as_of
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
