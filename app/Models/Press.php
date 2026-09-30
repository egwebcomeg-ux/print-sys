<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_internal
 * @property int $max_colors
 * @property array<array-key, mixed> $supported_cut_fractions
 * @property array<array-key, mixed>|null $supported_paper_categories
 * @property int $current_backlog_days
 * @property string|null $contact_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'name', 'is_internal', 'max_colors', 'supported_cut_fractions',
    'supported_paper_categories', 'current_backlog_days', 'contact_note',
])]
class Press extends Model
{
    /** @use HasFactory<PressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'max_colors' => 'integer',
            'supported_cut_fractions' => 'array',
            'supported_paper_categories' => 'array',
            'current_backlog_days' => 'integer',
        ];
    }

    /** @return HasMany<JobPressAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(JobPressAssignment::class);
    }
}
