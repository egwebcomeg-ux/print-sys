<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per manual press pick. Re-assigning a job appends a new row, so the
 * table doubles as a history of who routed the job where and when.
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
