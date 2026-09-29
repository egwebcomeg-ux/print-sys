<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncOdooInvoice;
use App\Models\Job;
use App\Services\Odoo\OdooInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OdooInvoiceSyncController extends Controller
{
    /** Queue (another) invoicing attempt — e.g. after a failure. */
    public function store(Job $job): RedirectResponse
    {
        if ($job->status !== JobStatus::Completed) {
            $this->toast('الفاتورة بتتعمل بس للشغلانة اللي خلصت ولسه ملهاش فاتورة', 'error');

            return back();
        }

        SyncOdooInvoice::dispatch($job);
        $this->toast('تم إرسال طلب الفاتورة لأودو');

        return back();
    }

    /** Manual fallback: record an invoice created by hand in Odoo. */
    public function manual(Request $request, Job $job, OdooInvoiceService $service): RedirectResponse
    {
        if ($job->status !== JobStatus::Completed) {
            $this->toast('الشغلانة لازم تكون خلصت ولسه ملهاش فاتورة', 'error');

            return back();
        }

        $data = $request->validate([
            'odoo_invoice_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\/._-]+$/', 'unique:odoo_invoice_syncs,odoo_invoice_id'],
        ], [
            'odoo_invoice_id.required' => 'اكتب رقم الفاتورة في أودو',
            'odoo_invoice_id.regex' => 'رقم الفاتورة بالشكل INV/2026/00042',
            'odoo_invoice_id.unique' => 'رقم الفاتورة ده متسجل على شغلانة تانية',
        ]);

        $service->recordManualInvoice($job, $data['odoo_invoice_id'], $request->user());
        $this->toast('تم تسجيل الفاتورة');

        return back();
    }
}
