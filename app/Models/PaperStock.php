<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $paper_grammage_id
 * @property string $sheet_width_cm
 * @property string $sheet_height_cm
 * @property int $quantity_sheets
 * @property int $reorder_level
 * @property string|null $location
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PaperGrammage $paperGrammage
 */
#[Fillable(['paper_grammage_id', 'sheet_width_cm', 'sheet_height_cm', 'quantity_sheets', 'reorder_level', 'location'])]
class PaperStock extends Model
{
    protected function casts(): array
    {
        return [
            'sheet_width_cm' => 'decimal:2',
            'sheet_height_cm' => 'decimal:2',
            'quantity_sheets' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    /** @return BelongsTo<PaperGrammage, $this> */
    public function paperGrammage(): BelongsTo
    {
        return $this->belongsTo(PaperGrammage::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function isLow(): bool
    {
        return $this->quantity_sheets < 0 || ($this->reorder_level > 0 && $this->quantity_sheets <= $this->reorder_level);
    }

    /** @param Builder<PaperStock> $query */
    public function scopeLow(Builder $query): void
    {
        $query->where(fn ($q) => $q->where('quantity_sheets', '<', 0)
            ->orWhere(fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('quantity_sheets', '<=', 'reorder_level')));
    }

    public function label(): string
    {
        $g = $this->paperGrammage;

        // LRI…PDI keeps "70×100" reading left-to-right inside Arabic text.
        return sprintf("%s %d جم — \u{2066}%s×%s\u{2069}", $g->paperType->name, $g->gsm, (float) $this->sheet_width_cm, (float) $this->sheet_height_cm);
    }
}
