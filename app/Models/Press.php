<?php

namespace App\Models;

use Database\Factories\PressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
