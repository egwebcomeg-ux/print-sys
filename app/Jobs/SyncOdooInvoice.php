<?php

namespace App\Jobs;

use App\Enums\JobStatus;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Services\Odoo\Exceptions\OdooRejectedException;
use App\Services\Odoo\OdooInvoiceService;
use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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

    /** Retries exhausted or Odoo rejected the invoice: tell invoicing staff. */
    public function failed(?Throwable $e): void
    {
        $job = $this->printJob->fresh();
        if ($job) {
            ActivityLog::record('job', $job->id, 'invoice_failed', 'فشل إرسال الفاتورة لأودو', ['error' => mb_substr((string) $e?->getMessage(), 0, 300)], null);
            Notify::ability('manage-invoicing', $job, 'فاتورة أودو فشلت — محتاجة إعادة محاولة أو تسجيل يدوي', 'error');
        }
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
