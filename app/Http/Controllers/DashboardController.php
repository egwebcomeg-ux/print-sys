<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\LeadStatus;
use App\Enums\OdooSyncStatus;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\Lead;
use App\Models\OdooInvoiceSync;
use App\Models\PaperStock;
use App\Support\CustomerBalance;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $counts = Job::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $failedInvoices = OdooInvoiceSync::query()
            ->where('status', OdooSyncStatus::Failed)
            ->whereHas('job', fn ($q) => $q->where('status', JobStatus::Completed))
            ->distinct('job_id')
            ->count('job_id');

        return Inertia::render('dashboard', [
            'statusCounts' => collect(JobStatus::cases())->map(fn (JobStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ]),
            'openLeads' => Lead::query()->whereIn('status', [LeadStatus::New, LeadStatus::Contacted])->count(),
            'failedInvoices' => $failedInvoices,
            'diesNeedingAttention' => CuttingDie::query()->where('condition', '!=', 'ready')->count(),
            // Dies within 10% of (or past) their estimated lifespan.
            'diesNearEndOfLife' => CuttingDie::query()
                ->whereNotNull('estimated_lifespan_jobs')
                ->whereRaw('jobs_run_count >= estimated_lifespan_jobs * 0.9')
                ->count(),
            // Total still owed by customers (positive balances only).
            'lowStock' => PaperStock::query()->low()->with('paperGrammage.paperType')->limit(6)->get()
                ->map(fn (PaperStock $s) => ['id' => $s->id, 'label' => $s->label(), 'quantity' => $s->quantity_sheets]),
            'receivables' => round(collect(CustomerBalance::for(Customer::query()->get(['id'])->map(fn (Customer $c): int => $c->id)->all()))->sum(fn ($b) => max(0, $b['balance'])), 2),
            'inProduction' => (int) ($counts[JobStatus::InProduction->value] ?? 0) + (int) ($counts[JobStatus::Approved->value] ?? 0),
            'recentJobs' => Job::query()->with('customer:id,name')->latest('id')->limit(8)->get()->map(fn (Job $job) => [
                'id' => $job->id,
                'name' => $job->displayName(),
                'customer' => $job->customer->name,
                'status' => $job->status->value,
                'statusLabel' => $job->status->label(),
                'finalPrice' => (float) $job->final_price_egp,
            ]),
        ]);
    }
}
