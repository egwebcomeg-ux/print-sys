<?php

namespace App\Listeners;

use App\Enums\JobStatus;
use App\Events\JobStatusChanged;
use App\Models\ActivityLog;
use App\Support\Notify;

/**
 * Logs every status change and tells the next team in line.
 */
class RecordJobStatusChange
{
    public function handle(JobStatusChanged $event): void
    {
        $job = $event->job;

        ActivityLog::record('job', $job->id, 'status', "«{$event->from->label()}» ← «{$event->to->label()}»", [
            'from' => $event->from->value,
            'to' => $event->to->value,
            'produced_quantity' => $event->to === JobStatus::Completed ? $job->produced_quantity : null,
        ], $event->actor);

        match ($event->to) {
            JobStatus::Approved => Notify::ability('run-production', $job, 'العميل وافق — شغلانة جديدة للإنتاج', 'info', $event->actor),
            JobStatus::Completed => Notify::ability('manage-invoicing', $job, 'الشغلانة خلصت — الفاتورة اتبعتت لأودو', 'success', $event->actor),
            JobStatus::Invoiced => Notify::ability('manage-invoicing', $job, 'اتعملت فاتورة الشغلانة في أودو', 'success', $event->actor),
            default => null,
        };
    }
}
