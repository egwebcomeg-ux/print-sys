<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Services\Odoo\Exceptions\OdooRejectedException;
use App\Services\Odoo\OdooInvoiceService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued Odoo invoicing for one completed job. Transient failures are
 * retried with backoff; Odoo rejections fail immediately so staff can use
 * the manual fallback. (Queue class — not the print `Job` model.)
 */
class SyncOdooInvoice implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly Job $printJob) {}

    /** @return list<int> seconds before each retry */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function uniqueId(): string
    {
        return (string) $this->printJob->id;
    }

    public function handle(OdooInvoiceService $service): void
    {
        $job = $this->printJob->fresh();

        // Already invoiced (e.g. manual fallback while this was queued).
        if (! $job || $job->status !== JobStatus::Completed || $job->successfulOdooSync()->exists()) {
            return;
        }

        try {
            $service->invoice($job);
        } catch (OdooRejectedException $e) {
            $this->fail($e);
        }
    }
}
