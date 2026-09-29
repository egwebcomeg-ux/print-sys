<?php

namespace App\Models;

use Database\Factories\PaperSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_note', 'performance_notes'])]
class PaperSupplier extends Model
{
    /** @use HasFactory<PaperSupplierFactory> */
    use HasFactory;

    /** @return HasMany<PaperGrammagePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PaperGrammagePrice::class);
    }
}
