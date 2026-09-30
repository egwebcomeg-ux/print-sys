<?php

namespace App\Models;

use App\Enums\PaperCategory;
use Carbon\CarbonImmutable;
use Database\Factories\PaperTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property PaperCategory $category
 * @property int $sheet_width_cm
 * @property int $sheet_height_cm
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'category', 'sheet_width_cm', 'sheet_height_cm'])]
class PaperType extends Model
{
    /** @use HasFactory<PaperTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => PaperCategory::class,
            'sheet_width_cm' => 'integer',
            'sheet_height_cm' => 'integer',
        ];
    }

    /** @return HasMany<PaperGrammage, $this> */
    public function grammages(): HasMany
    {
        return $this->hasMany(PaperGrammage::class)->orderBy('gsm');
    }
}
