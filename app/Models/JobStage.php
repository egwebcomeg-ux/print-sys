<?php

namespace App\Models;

use App\Enums\JobStageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'name', 'sort_order', 'status', 'started_at', 'completed_at'])]
class JobStage extends Model
{
    protected function casts(): array
    {
        return [
            'status' => JobStageStatus::class,
            'sort_order' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
