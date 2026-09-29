<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\Jobs\JobLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobCompletionController extends Controller
{
    /**
     * Confirm the actual post-waste quantity and mark the job completed.
     * Invoicing happens afterwards and never blocks this step.
     */
    public function store(Request $request, Job $job, JobLifecycleService $lifecycle): RedirectResponse
    {
        $data = $request->validate([
            'produced_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], ['produced_quantity.required' => 'اكتب الكمية الفعلية بعد الهالك']);

        $lifecycle->transition($job, JobStatus::Completed, $request->user(), $data);
        $this->toast('تم تأكيد الكمية النهائية — الشغلانة خلصت');

        return back();
    }
}
