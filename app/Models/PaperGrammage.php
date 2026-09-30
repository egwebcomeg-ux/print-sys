<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaperGrammageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $paper_type_id
 * @property int $gsm
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read int|null $prices_count
 */
#[Fillable(['paper_type_id', 'gsm'])]
class PaperGrammage extends Model
{
    /** @use HasFactory<PaperGrammageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['gsm' => 'integer'];
    }

    /** @return BelongsTo<PaperType, $this> */
    public function paperType(): BelongsTo
    {
        return $this->belongsTo(PaperType::class);
    }

    /**
     * Every supplier's current price for this grammage — several coexist on
     * purpose so staff can compare (multi-supplier business rule).
     *
     * @return HasMany<PaperGrammagePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PaperGrammagePrice::class)->orderBy('price_per_ton_egp');
    }

    /**
     * The cheapest supplier price — only the default suggestion, never the
     * only option.
     *
     * @return HasOne<PaperGrammagePrice, $this>
     */
    public function cheapestPrice(): HasOne
    {
        return $this->hasOne(PaperGrammagePrice::class)->ofMany('price_per_ton_egp', 'min');
    }
}
