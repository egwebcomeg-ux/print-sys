<?php

namespace App\Http\Controllers;

use App\Enums\JobStageStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Press;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة الإنتاج — every approved / in-production job grouped by the press it
 * was routed to, with its current stage, so the floor sees the queue at a glance.
 */
class ProductionBoardController extends Controller
{
    public function __invoke(): Response
    {
        $jobs = Job::query()
            ->whereIn('status', [JobStatus::Approved, JobStatus::InProduction])
            ->with(['customer:id,name', 'stages', 'latestPressAssignment.press:id,name'])
            ->orderBy('id')
            ->get()
            ->map(function (Job $job) {
                $current = $job->stages->firstWhere('status', JobStageStatus::InProgress)
                    ?? $job->stages->firstWhere('status', JobStageStatus::Pending);
                $done = $job->stages->where('status', JobStageStatus::Done)->count();

                return [
                    'id' => $job->id,
                    'name' => $job->displayName(),
                    'customer' => $job->customer->name,
                    'quantity' => $job->quantity,
                    'status' => $job->status->value,
                    'statusLabel' => $job->status->label(),
                    'pressId' => $job->latestPressAssignment?->press_id,
                    'currentStage' => $current?->name,
                    'currentStageStatus' => $current?->status->value,
                    'stagesDone' => $done,
                    'stagesTotal' => $job->stages->count(),
                    'daysSinceApproval' => (int) $job->updated_at?->diffInDays(now()),
                ];
            });

        $presses = Press::query()->orderByDesc('is_internal')->orderBy('name')->get()
            ->map(fn (Press $press) => [
                'id' => $press->id,
                'name' => $press->name,
                'isInternal' => $press->is_internal,
                'backlogDays' => $press->current_backlog_days,
                'jobs' => $jobs->where('pressId', $press->id)->values(),
            ]);

        return Inertia::render('production/board', [
            'presses' => $presses,
            'unassigned' => $jobs->whereNull('pressId')->values(),
            'stageNames' => JobStage::query()
                ->whereIn('job_id', $jobs->pluck('id'))
                ->distinct()->orderBy('sort_order')->pluck('name')->values(),
        ]);
    }
}
