<?php

namespace App\Events;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class JobStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Job $job,
        public readonly JobStatus $from,
        public readonly JobStatus $to,
        public readonly ?User $actor,
    ) {}
}
