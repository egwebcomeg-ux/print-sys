<?php

namespace App\Actions\Jobs;

use App\Enums\LeadStatus;
use App\Models\Job;
use App\Models\Lead;

/**
 * A lead that gets priced becomes "quoted" and remembers its job.
 */
class LinkLeadToJob
{
    public function handle(int $leadId, Job $job): void
    {
        $lead = Lead::query()->find($leadId);

        if (! $lead) {
            return;
        }

        $lead->update([
            'customer_id' => $lead->customer_id ?? $job->customer_id,
            'converted_job_id' => $job->id,
            'status' => LeadStatus::Quoted,
        ]);
    }
}
