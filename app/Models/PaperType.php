<?php

namespace App\Models;

use App\Enums\PaperCategory;
use Database\Factories\PaperTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
