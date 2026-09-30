<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per manual press pick. Re-assigning a job appends a new row, so the
 * table doubles as a history of who routed the job where and when.
 *
 * @property int $id
 * @property int $job_id
 * @property int $press_id
 * @property int|null $assigned_by_user_id
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['job_id', 'press_id', 'assigned_by_user_id', 'assigned_at'])]
class JobPressAssignment extends Model
{
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<Press, $this> */
    public function press(): BelongsTo
    {
        return $this->belongsTo(Press::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
