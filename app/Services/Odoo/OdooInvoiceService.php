<?php

namespace App\Services\Odoo;

use App\Enums\JobStatus;
use App\Enums\OdooSyncStatus;
use App\Models\Job;
use App\Models\OdooInvoiceSync;
use App\Models\User;
use App\Services\Jobs\JobLifecycleService;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The single Odoo integration: create the customer invoice for a completed
 * job from its produced (post-waste) quantity. Every attempt is logged in
 * `odoo_invoice_syncs`. Safe to retry: an invoice already carrying the job's
 * ref (PP-{id}) is reused instead of created twice.
 */
class OdooInvoiceService
{
    public function __construct(
        private readonly OdooClientInterface $odoo,
        private readonly JobLifecycleService $lifecycle,
    ) {}

    /**
     * @throws Throwable the attempt is logged as failed, then rethrown so the
     *                   queue can decide whether to retry
     */
    public function invoice(Job $job): OdooInvoiceSync
    {
        $job->loadMissing('customer');

        $sync = $job->odooSyncs()->create(['status' => OdooSyncStatus::Pending]);

        try {
            $payload = $this->payload($job);
            $sync->update(['status' => OdooSyncStatus::Sent, 'request_payload' => $payload]);

            $existing = $this->odoo->executeKw('account.move', 'search_read', [[
                ['ref', '=', $payload['ref']],
                ['move_type', '=', 'out_invoice'],
            ]], ['fields' => ['id', 'name', 'state'], 'limit' => 1]);

            $moveId = $existing[0]['id'] ?? $this->odoo->executeKw('account.move', 'create', [$payload]);
            $sync->update(['odoo_invoice_id' => (string) $moveId]);

            if (config('odoo.auto_post') && ($existing[0]['state'] ?? 'draft') === 'draft') {
                $this->odoo->executeKw('account.move', 'action_post', [[$moveId]]);
            }

            $move = $this->odoo->executeKw('account.move', 'read', [[$moveId]], ['fields' => ['name', 'state']])[0] ?? [];

            $sync->update([
                'status' => OdooSyncStatus::Success,
                'response_payload' => [
                    'id' => $moveId,
                    'name' => $move['name'] ?? null,
                    'state' => $move['state'] ?? null,
                    'reused' => isset($existing[0]),
                    'billed_total' => self::billedTotal($payload),
                ],
                'synced_at' => now(),
                'error_message' => null,
            ]);

            $this->markInvoiced($job, null);
        } catch (Throwable $e) {
            $sync->update([
                'status' => OdooSyncStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'synced_at' => now(),
            ]);

            throw $e;
        }

        return $sync;
    }

    /** Untaxed line total of an account.move payload (qty × unit price, 2 dp). */
    public static function billedTotal(array $payload): float
    {
        $total = 0.0;
        foreach ($payload['invoice_line_ids'] ?? [] as $command) {
            $line = $command[2] ?? [];
            $total += ((float) ($line['quantity'] ?? 0)) * ((float) ($line['price_unit'] ?? 0));
        }

        return round($total, 2);
    }

    /**
     * Manual fallback: staff created the invoice by hand in Odoo and record its id.
     * When Odoo is configured, the id must exist there as a customer invoice.
     *
     * @throws ValidationException
     */
    public function recordManualInvoice(Job $job, string $odooInvoiceId, User $user): OdooInvoiceSync
    {
        if (! config('odoo.fake')) {
            $found = $this->odoo->executeKw('account.move', 'search_read', [[
                ['name', '=', $odooInvoiceId], ['move_type', '=', 'out_invoice'],
            ]], ['fields' => ['id'], 'limit' => 1]);

            if (empty($found)) {
                throw ValidationException::withMessages(['odoo_invoice_id' => 'مفيش فاتورة عميل بالرقم ده في أودو']);
            }
        }

        $sync = $job->odooSyncs()->create([
            'status' => OdooSyncStatus::Success,
            'odoo_invoice_id' => $odooInvoiceId,
            'response_payload' => ['manual' => true, 'recorded_by' => $user->id, 'name' => $odooInvoiceId],
            'synced_at' => now(),
        ]);

        $this->markInvoiced($job, $user);

        return $sync;
    }

    /**
     * The `account.move` create values. Billed on `produced_quantity` (the
     * actual post-waste amount), priced at the quoted unit price.
     *
     * @return array<string, mixed>
     */
    public function payload(Job $job): array
    {
        $produced = (int) $job->produced_quantity;
        $final = (float) $job->final_price_egp;

        if ($job->quantity) {
            // Fixed-price rule: within the billing tolerance the customer pays the
            // quoted total (industry ±10% over/under); outside it, the produced
            // quantity × the quoted unit price (2 dp — Odoo's default precision).
            $unitPrice = round($final / $job->quantity, 2);
            $deviation = abs($produced - $job->quantity) / $job->quantity * 100;
            if ($deviation <= Settings::billingTolerancePercent()) {
                $lineQty = 1;
                $unitPrice = round($final, 2);
                $lineName = "{$job->displayName()} — {$job->quantity} قطعة (المنتج فعليًا {$produced})";
            } else {
                $lineQty = $produced;
                $lineName = $job->displayName();
            }
        } else {
            // Manual job quoted as a lump sum (no piece count): bill the quoted
            // total as one line, noting the produced quantity.
            $lineQty = 1;
            $unitPrice = round($final, 2);
            $lineName = "{$job->displayName()} — الكمية المنتجة {$produced}";
        }

        $line = ['name' => $lineName, 'quantity' => $lineQty, 'price_unit' => $unitPrice];
        if ($taxId = config('odoo.tax_id')) {
            $line['tax_ids'] = [[6, 0, [$taxId]]];
        }

        $payload = [
            'move_type' => 'out_invoice',
            'partner_id' => $this->partnerId($job),
            'invoice_date' => now()->toDateString(),
            'ref' => "PP-{$job->id}",
            'invoice_line_ids' => [[0, 0, $line]],
        ];

        if ($journalId = config('odoo.journal_id')) {
            $payload['journal_id'] = $journalId;
        }

        return $payload;
    }

    /** Find (by exact name) or create the customer in Odoo, caching the id. */
    private function partnerId(Job $job): int
    {
        $customer = $job->customer;

        if ($customer->odoo_partner_id) {
            return (int) $customer->odoo_partner_id;
        }

        $found = $this->odoo->executeKw('res.partner', 'search_read', [[['name', '=', $customer->name]]], ['fields' => ['id'], 'limit' => 1]);

        $partnerId = $found[0]['id'] ?? $this->odoo->executeKw('res.partner', 'create', [array_filter([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'customer_rank' => 1,
        ])]);

        $customer->update(['odoo_partner_id' => (string) $partnerId]);

        return (int) $partnerId;
    }

    private function markInvoiced(Job $job, ?User $actor): void
    {
        if ($job->fresh()->status === JobStatus::Completed) {
            $this->lifecycle->transition($job->fresh(), JobStatus::Invoiced, $actor);
        }
    }
}
