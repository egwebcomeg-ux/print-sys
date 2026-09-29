<?php

namespace App\Services\Jobs;

use App\Enums\JobStatus;
use App\Enums\LeadStatus;
use App\Events\JobStatusChanged;
use App\Models\Job;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place a job's status changes:
 * draft → quoted → approved → in_production → completed → invoiced.
 */
class JobLifecycleService
{
    /**
     * @param  array{produced_quantity?: int}  $data
     *
     * @throws ValidationException when the move isn't allowed
     * @throws AuthorizationException when the actor's role can't make it
     */
    public function transition(Job $job, JobStatus $to, ?User $actor, array $data = []): Job
    {
        $from = $job->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "مينفعش تنقل الشغلانة من «{$from->label()}» لـ «{$to->label()}»",
            ]);
        }

        // A null actor is the system (the queued Odoo sync). The status
        // endpoint never offers `invoiced`; only the Odoo flows reach it.
        if ($actor !== null && ! $actor->can(self::abilityFor($to))) {
            throw new AuthorizationException('صلاحيتك متسمحش بالخطوة دي');
        }

        if ($to === JobStatus::Completed) {
            $produced = (int) ($data['produced_quantity'] ?? 0);
            if ($produced < 1) {
                throw ValidationException::withMessages([
                    'produced_quantity' => 'اكتب الكمية الفعلية بعد الهالك',
                ]);
            }
            $job->produced_quantity = $produced;
        }

        DB::transaction(function () use ($job, $to) {
            $job->status = $to;
            $job->save();

            if ($to === JobStatus::Approved) {
                JobStageTemplate::seedFor($job);

                Lead::query()->where('converted_job_id', $job->id)->update(['status' => LeadStatus::Won]);
            }
        });

        JobStatusChanged::dispatch($job, $from, $to, $actor);

        return $job;
    }

    public static function abilityFor(JobStatus $to): string
    {
        return match ($to) {
            JobStatus::Quoted, JobStatus::Approved => 'advance-sales-status',
            JobStatus::InProduction, JobStatus::Completed => 'run-production',
            JobStatus::Invoiced => 'manage-invoicing',
            JobStatus::Draft => 'create-jobs',
        };
    }
}
