<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * سجل النشاط — the full audit trail, newest first.
 */
class ActivityLogController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/activity-log', [
            'entries' => ActivityLog::query()->with('user:id,name')->latest('id')->paginate(50)
                ->through(fn (ActivityLog $a) => [
                    'id' => $a->id,
                    'subjectType' => $a->subject_type,
                    'subjectId' => $a->subject_id,
                    'action' => $a->action,
                    'description' => $a->description,
                    'user' => $a->user->name ?? 'السيستم',
                    'at' => $a->created_at->toIso8601String(),
                ]),
        ]);
    }
}
