<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\Jobs\JobLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobStatusController extends Controller
{
    /**
     * Move a job one step forward. Completion (needs the produced quantity)
     * and invoicing (Odoo) have their own endpoints.
     */
    public function update(Request $request, Job $job, JobLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(JobStatus::class)->only([
                JobStatus::Quoted, JobStatus::Approved, JobStatus::InProduction,
            ])],
        ]);

        $to = JobStatus::from($data['status']);
        $lifecycle->transition($job, $to, $request->user());
        $this->toast("الشغلانة بقت: {$to->label()}");

        return back();
    }
}
