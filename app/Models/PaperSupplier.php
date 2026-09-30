<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaperSupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $contact_note
 * @property string|null $performance_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
