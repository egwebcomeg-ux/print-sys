<?php

namespace App\Models;

use App\Enums\ClosureType;
use App\Enums\CutFraction;
use App\Enums\DieCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cutting die (اسطمبة). Named CuttingDie because `die` is a reserved word in PHP.
 */
#[Fillable([
    'code', 'name', 'length_cm', 'width_cm', 'depth_cm', 'closure_type', 'rack_location',
    'ups_on_cut_sheet', 'cut_fraction', 'condition', 'jobs_run_count', 'estimated_lifespan_jobs',
])]
class CuttingDie extends Model
{
    /** @use HasFactory<CuttingDieFactory> */
    use HasFactory;

    protected $table = 'dies';

    protected function casts(): array
    {
        return [
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'depth_cm' => 'decimal:2',
            'closure_type' => ClosureType::class,
            'cut_fraction' => CutFraction::class,
            'condition' => DieCondition::class,
            'ups_on_cut_sheet' => 'integer',
            'jobs_run_count' => 'integer',
            'estimated_lifespan_jobs' => 'integer',
        ];
    }

    /** @return HasMany<Job, $this> */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
