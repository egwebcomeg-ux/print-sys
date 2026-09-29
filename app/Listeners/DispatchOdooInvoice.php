<?php

namespace App\Listeners;

use App\Enums\JobStatus;
use App\Events\JobStatusChanged;
use App\Jobs\SyncOdooInvoice;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * When a job is completed, queue its (single) Odoo invoice. Completion must
 * never fail because of invoicing, so dispatch errors are only logged.
 */
class DispatchOdooInvoice
{
    public function handle(JobStatusChanged $event): void
    {
        if ($event->to !== JobStatus::Completed || ! config('odoo.auto_invoice_on_complete')) {
            return;
        }

        try {
            SyncOdooInvoice::dispatch($event->job);
        } catch (Throwable $e) {
            Log::warning('Odoo invoice dispatch failed; retry from the job page', [
                'job_id' => $event->job->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
