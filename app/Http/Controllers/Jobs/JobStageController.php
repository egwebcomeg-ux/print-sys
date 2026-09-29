<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStageStatus;
use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobStageController extends Controller
{
    public function update(Request $request, Job $job, JobStage $stage): RedirectResponse
    {
        abort_unless($stage->job_id === $job->id, 404);

        if (! in_array($job->status, [JobStatus::Approved, JobStatus::InProduction], true)) {
            $this->toast('المراحل بتتحدث بس والشغلانة في الإنتاج', 'error');

            return back();
        }

        $status = JobStageStatus::from($request->validate([
            'status' => ['required', Rule::enum(JobStageStatus::class)],
        ])['status']);

        $stage->status = $status;
        $stage->started_at = match ($status) {
            JobStageStatus::Pending => null,
            default => $stage->started_at ?? now(),
        };
        $stage->completed_at = $status === JobStageStatus::Done ? ($stage->completed_at ?? now()) : null;
        $stage->save();

        return back();
    }
}
