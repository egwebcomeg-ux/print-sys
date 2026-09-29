<?php

namespace App\Models;

use App\Enums\BoxShape;
use App\Enums\BoxType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\Lamination;
use App\Enums\OdooSyncStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A print job (شغلانة) — not a queue job. Either a die-cut box
 * (QuickBoxPricingCalculator) or a manual paper job (ManualJobCostingCalculator).
 */
#[Fillable([
    'customer_id', 'job_type', 'title', 'box_type', 'box_shape', 'length_cm', 'width_cm', 'depth_cm',
    'quantity', 'paper_grammage_id', 'paper_grammage_price_id', 'print_colors', 'lamination',
    'is_using_existing_die', 'die_id', 'raw_sheets_needed', 'ups_per_raw_sheet', 'interlocked',
    'base_cost_egp', 'margin_percent', 'final_price_egp', 'quote_snapshot', 'produced_quantity', 'status',
])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $attributes = [
        'job_type' => 'box',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'job_type' => JobType::class,
            'status' => JobStatus::class,
            'box_type' => BoxType::class,
            'box_shape' => BoxShape::class,
            'lamination' => Lamination::class,
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'depth_cm' => 'decimal:2',
            'quantity' => 'integer',
            'print_colors' => 'integer',
            'is_using_existing_die' => 'boolean',
            'raw_sheets_needed' => 'integer',
            'ups_per_raw_sheet' => 'integer',
            'interlocked' => 'boolean',
            'base_cost_egp' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'final_price_egp' => 'decimal:2',
            'quote_snapshot' => 'array',
            'produced_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<PaperGrammage, $this> */
    public function paperGrammage(): BelongsTo
    {
        return $this->belongsTo(PaperGrammage::class);
    }

    /** @return BelongsTo<PaperGrammagePrice, $this> */
    public function paperGrammagePrice(): BelongsTo
    {
        return $this->belongsTo(PaperGrammagePrice::class);
    }

    /** @return BelongsTo<CuttingDie, $this> */
    public function die(): BelongsTo
    {
        return $this->belongsTo(CuttingDie::class, 'die_id');
    }

    /** @return HasMany<JobCostLine, $this> */
    public function costLines(): HasMany
    {
        return $this->hasMany(JobCostLine::class);
    }

    /** @return HasMany<JobPaperItem, $this> */
    public function paperItems(): HasMany
    {
        return $this->hasMany(JobPaperItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<JobStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(JobStage::class)->orderBy('sort_order');
    }

    /** @return HasMany<JobPressAssignment, $this> */
    public function pressAssignments(): HasMany
    {
        return $this->hasMany(JobPressAssignment::class)->latest('assigned_at')->latest('id');
    }

    /** @return HasOne<JobPressAssignment, $this> */
    public function latestPressAssignment(): HasOne
    {
        return $this->hasOne(JobPressAssignment::class)->latestOfMany();
    }

    /** @return HasMany<OdooInvoiceSync, $this> */
    public function odooSyncs(): HasMany
    {
        return $this->hasMany(OdooInvoiceSync::class)->latest('id');
    }

    /** @return HasOne<OdooInvoiceSync, $this> */
    public function successfulOdooSync(): HasOne
    {
        return $this->hasOne(OdooInvoiceSync::class)->where('status', OdooSyncStatus::Success);
    }

    /** @param Builder<Job> $query */
    public function scopeBox(Builder $query): void
    {
        $query->where('job_type', JobType::Box);
    }

    /** @param Builder<Job> $query */
    public function scopeManual(Builder $query): void
    {
        $query->where('job_type', JobType::Manual);
    }

    /** @param Builder<Job> $query */
    public function scopeWithStatus(Builder $query, JobStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Human-readable name used in lists and on the Odoo invoice line:
     * the typed title, or "علبة دواء 9×5×3" for box jobs without one.
     */
    public function displayName(): string
    {
        if ($this->title) {
            return $this->title;
        }

        if ($this->job_type === JobType::Box && $this->box_type) {
            return sprintf(
                'علبة %s %s×%s×%s',
                $this->box_type->label(),
                (float) $this->length_cm,
                (float) $this->width_cm,
                (float) $this->depth_cm,
            );
        }

        return 'شغلانة #'.$this->id;
    }
}
