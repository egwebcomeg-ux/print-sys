<?php

namespace App\Services\Jobs;

use App\Enums\JobStageStatus;
use App\Enums\JobType;
use App\Models\Job;

/**
 * Creates a job's production stages from config('pantopack.default_stages').
 */
class JobStageTemplate
{
    /** @return list<string> */
    public static function namesFor(Job $job): array
    {
        $templates = config('pantopack.default_stages');

        $key = $job->job_type === JobType::Manual ? 'manual' : $job->box_shape?->value;

        return $templates[$key] ?? $templates['default'];
    }

    public static function seedFor(Job $job): void
    {
        if ($job->stages()->exists()) {
            return;
        }

        foreach (self::namesFor($job) as $order => $name) {
            $job->stages()->create([
                'name' => $name,
                'sort_order' => $order,
                'status' => JobStageStatus::Pending,
            ]);
        }
    }
}
