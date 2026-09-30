<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (bell icon) about a job. Database channel only —
 * no email/WhatsApp (deferred per the brief).
 */
class JobNotification extends Notification
{
    use Queueable;

    /** @param 'info'|'success'|'warning'|'error' $level */
    public function __construct(
        public readonly Job $job,
        public readonly string $title,
        public readonly string $level = 'info',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => "#{$this->job->id} — {$this->job->displayName()}",
            'level' => $this->level,
            'job_id' => $this->job->id,
            'url' => route('jobs.show', $this->job, false),
        ];
    }
}
