<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\Press;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobPressAssignmentController extends Controller
{
    /**
     * Record a human's press pick. Always a manual click (business rule) —
     * nothing in the system assigns presses on its own. Re-assigning appends
     * a new row so the history is kept.
     */
    public function store(Request $request, Job $job): RedirectResponse
    {
        if (! in_array($job->status, [JobStatus::Approved, JobStatus::InProduction], true)) {
            $this->toast('توزيع المطبعة بيبقى بعد موافقة العميل', 'error');

            return back();
        }

        $data = $request->validate([
            'press_id' => ['required', 'integer', 'exists:presses,id'],
        ]);

        $job->pressAssignments()->create([
            'press_id' => $data['press_id'],
            'assigned_by_user_id' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        $pressName = (string) Press::query()->whereKey($data['press_id'])->value('name');
        ActivityLog::record('job', $job->id, 'press', "اتوزعت على {$pressName}");
        Notify::ability('run-production', $job, "اتوزعت على {$pressName}");
        $this->toast("تم توزيع الشغلانة على {$pressName}");

        return back();
    }
}
