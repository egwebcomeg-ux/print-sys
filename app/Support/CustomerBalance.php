<?php

namespace App\Support;

use App\Enums\JobStatus;
use App\Enums\OdooSyncStatus;
use App\Models\Job;
use App\Models\OdooInvoiceSync;
use App\Models\Payment;

/**
 * What each customer has been invoiced (incl. VAT), has paid, and still owes.
 *
 * "Invoiced" uses the amount actually billed to Odoo (after the billing
 * tolerance rule) when it's recorded, else the job's final price.
 */
class CustomerBalance
{
    /**
     * @param  array<int>  $customerIds
     * @return array<int, array{invoiced: float, paid: float, balance: float}>
     */
    public static function for(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $vat = 1 + Settings::company()['vatPercent'] / 100;

        $jobs = Job::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', JobStatus::Invoiced)
            ->get(['id', 'customer_id', 'final_price_egp']);

        // Billed (untaxed) amount per job from the successful sync, when present.
        $billed = OdooInvoiceSync::query()
            ->whereIn('job_id', $jobs->pluck('id'))
            ->where('status', OdooSyncStatus::Success)
            ->get(['job_id', 'response_payload'])
            ->mapWithKeys(fn (OdooInvoiceSync $s) => [$s->job_id => $s->response_payload['billed_total'] ?? null]);

        $paid = Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(amount_egp) as total')
            ->pluck('total', 'customer_id');

        $result = [];
        foreach ($customerIds as $id) {
            $invoiced = $jobs->where('customer_id', $id)->sum(
                fn (Job $job) => (float) ($billed[$job->id] ?? $job->final_price_egp) * $vat,
            );
            $paidTotal = (float) ($paid[$id] ?? 0);

            $result[$id] = [
                'invoiced' => round($invoiced, 2),
                'paid' => round($paidTotal, 2),
                // Positive = the customer owes us; negative = credit / deposits ahead of invoices.
                'balance' => round($invoiced - $paidTotal, 2),
            ];
        }

        return $result;
    }

    /** @return array{invoiced: float, paid: float, balance: float} */
    public static function forCustomer(int $customerId): array
    {
        return self::for([$customerId])[$customerId];
    }
}
