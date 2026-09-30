<?php

namespace App\Http\Resources;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Jobs\JobEditController;
use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobCostLine;
use App\Models\JobPaperItem;
use App\Models\JobPressAssignment;
use App\Models\JobStage;
use App\Models\OdooInvoiceSync;
use App\Models\PaperPriceChange;
use App\Services\Jobs\JobLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the job page (pages/jobs/show.tsx) needs.
 *
 * @mixin Job
 */
class JobDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $next = $this->status->manualNext();
        $latestAssignment = $this->pressAssignments->first();

        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'title' => $this->title,
            'type' => $this->job_type->value,
            'typeLabel' => $this->job_type->label(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'statuses' => JobStatus::options(),
            'next' => $next ? [
                'value' => $next->value,
                'label' => $next->label(),
                'allowed' => (bool) $request->user()?->can(JobLifecycleService::abilityFor($next)),
            ] : null,
            'customer' => $this->customer->only(['id', 'name', 'phone', 'email']),
            'editable' => JobEditController::editable($this->resource) && (bool) $request->user()?->can('create-jobs'),
            'warnings' => $this->warnings(),
            'activity' => ActivityLog::query()->with('user:id,name')
                ->where('subject_type', 'job')->where('subject_id', $this->id)
                ->latest('id')->limit(30)->get()
                ->map(fn (ActivityLog $a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'user' => $a->user?->name ?? 'السيستم',
                    'at' => $a->created_at?->toIso8601String(),
                ]),
            'createdAt' => $this->created_at?->toIso8601String(),

            'box' => $this->job_type === JobType::Box ? [
                'boxType' => $this->box_type?->label(),
                'shape' => $this->box_shape?->label(),
                'dimensions' => sprintf('%s×%s×%s', (float) $this->length_cm, (float) $this->width_cm, (float) $this->depth_cm),
                'paper' => $this->paperGrammage
                    ? "{$this->paperGrammage->paperType->name} {$this->paperGrammage->gsm} جم"
                    : null,
                'supplier' => $this->paperGrammagePrice?->supplier->name,
                'pricePerTon' => $this->paperGrammagePrice ? (float) $this->paperGrammagePrice->price_per_ton_egp : null,
                'printColors' => $this->print_colors,
                'lamination' => $this->lamination->label(),
                'die' => $this->die ? "{$this->die->code} — {$this->die->name}" : null,
                'dieLocation' => $this->die?->rack_location,
                'newDie' => ! $this->is_using_existing_die,
                'rawSheetsNeeded' => $this->raw_sheets_needed,
                'upsPerRawSheet' => $this->ups_per_raw_sheet,
                'interlocked' => $this->interlocked,
            ] : null,

            'quantity' => $this->quantity,
            'producedQuantity' => $this->produced_quantity,
            'baseCost' => (float) $this->base_cost_egp,
            'marginPercent' => (float) $this->margin_percent,
            'finalPrice' => (float) $this->final_price_egp,
            'unitPrice' => $this->quantity ? round((float) $this->final_price_egp / $this->quantity, 2) : null,

            'costLines' => $this->costLines->map(fn (JobCostLine $line) => [
                'id' => $line->id,
                'label' => $line->label,
                'amount' => (float) $line->amount_egp,
            ]),
            'paperItems' => $this->paperItems->map(fn (JobPaperItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'paper' => "{$item->paperGrammage->paperType->name} {$item->paperGrammage->gsm} جم",
                'supplier' => $item->paperGrammagePrice?->supplier->name,
                'size' => sprintf('%s×%s', (float) $item->sheet_width_cm, (float) $item->sheet_height_cm),
                'sheets' => $item->sheets_count,
                'weightKg' => (float) $item->weight_kg,
                'cost' => (float) $item->cost_egp,
            ]),

            'stages' => $this->stages->map(fn (JobStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'status' => $stage->status->value,
                'statusLabel' => $stage->status->label(),
                'startedAt' => $stage->started_at?->toIso8601String(),
                'completedAt' => $stage->completed_at?->toIso8601String(),
            ]),

            'routing' => [
                'requirements' => $this->routingRequirements(),
                'currentPressId' => $latestAssignment ? (string) $latestAssignment->press_id : null,
                'history' => $this->pressAssignments->map(fn (JobPressAssignment $a) => [
                    'id' => $a->id,
                    'press' => $a->press->name,
                    'by' => $a->assignedBy?->name,
                    'at' => $a->assigned_at?->toIso8601String(),
                ]),
            ],

            'odoo' => [
                'syncs' => $this->odooSyncs->map(fn (OdooInvoiceSync $sync) => [
                    'id' => $sync->id,
                    'status' => $sync->status->value,
                    'statusLabel' => $sync->status->label(),
                    'invoiceId' => $sync->odoo_invoice_id,
                    'invoiceName' => $this->invoiceLabel($sync),
                    'manual' => $sync->isManual(),
                    // Raw Odoo errors can reveal internals — only invoicing staff see them.
                    'error' => $sync->error_message === null ? null
                        : ($request->user()?->can('manage-invoicing') ? $sync->error_message : 'فشل الإرسال — راجع المبيعات'),
                    'billedTotal' => $sync->response_payload['billed_total'] ?? null,
                    'at' => ($sync->synced_at ?? $sync->created_at)?->toIso8601String(),
                ]),
                'invoiced' => $this->status === JobStatus::Invoiced,
                'canInvoice' => $this->status === JobStatus::Completed,
            ],
        ];
    }

    /**
     * Things staff should see before moving the job on.
     *
     * @return list<array{type: string, message: string}>
     */
    private function warnings(): array
    {
        $warnings = [];
        $open = in_array($this->status, [JobStatus::Draft, JobStatus::Quoted], true);

        // Credit limit: open (not yet invoiced) work for this customer, this job included.
        $limit = $this->customer->credit_limit_egp;
        if ($limit !== null && $this->status !== JobStatus::Invoiced) {
            $exposure = (float) Job::query()
                ->where('customer_id', $this->customer_id)
                ->whereIn('status', [JobStatus::Approved, JobStatus::InProduction, JobStatus::Completed])
                ->whereKeyNot($this->id)
                ->sum('final_price_egp') + (float) $this->final_price_egp;
            if ($exposure > (float) $limit) {
                $warnings[] = ['type' => 'credit', 'message' => sprintf('شغل العميل المفتوح (%s ج) بيعدّي حد الائتمان (%s ج)', number_format($exposure, 2), number_format((float) $limit, 2))];
            }
        }

        // Paper price moved since this quote was priced.
        if ($open) {
            $priceIds = collect([$this->paper_grammage_price_id])->merge($this->paperItems->pluck('paper_grammage_price_id'))->filter();
            $moved = $priceIds->isNotEmpty() && PaperPriceChange::query()
                ->whereIn('paper_grammage_price_id', $priceIds)
                ->where('created_at', '>', $this->updated_at)
                ->exists();
            if ($moved) {
                $warnings[] = ['type' => 'price', 'message' => 'سعر الورق اتغيّر من ساعة ما الشغلانة اتسعّرت — اعمل إعادة تسعير قبل ما تبعت العرض'];
            }
        }

        return $warnings;
    }

    /**
     * Odoo names draft invoices "/" until they're posted (ODOO_AUTO_POST=false).
     */
    private function invoiceLabel(OdooInvoiceSync $sync): ?string
    {
        $name = $sync->response_payload['name'] ?? null;

        if ($name && $name !== '/') {
            return $name;
        }

        return $sync->odoo_invoice_id ? "مسودة في أودو #{$sync->odoo_invoice_id}" : null;
    }

    /**
     * What PressRoutingSelector filters on (its JobRoutingRequirements).
     *
     * @return array<string, mixed>
     */
    private function routingRequirements(): array
    {
        $category = $this->job_type === JobType::Box
            ? $this->paperGrammage?->paperType->category->value
            : $this->paperItems->first()?->paperGrammage->paperType->category->value;

        return [
            // Same default as the box calculator when no die is used.
            'cutFraction' => $this->die?->cut_fraction->value ?? '1/2',
            'printColors' => $this->print_colors,
            'paperCategory' => $category ?? 'duplex_grey_back',
            'quantity' => (int) ($this->quantity ?? 0),
        ];
    }
}
