<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\PressResource;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Press;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'type' => ['nullable', Rule::enum(JobType::class)],
            'customer' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $jobs = Job::query()
            ->with('customer:id,name')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('job_type', $t))
            ->when($filters['customer'] ?? null, fn ($q, $c) => $q->where('customer_id', $c))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$s}%")
                ->orWhere('id', ltrim($s, '#'))
                ->orWhereRelation('customer', 'name', 'like', "%{$s}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Job $job) => [
                'id' => $job->id,
                'name' => $job->displayName(),
                'customer' => $job->customer->name,
                'type' => $job->job_type->value,
                'typeLabel' => $job->job_type->label(),
                'quantity' => $job->quantity,
                'producedQuantity' => $job->produced_quantity,
                'finalPrice' => (float) $job->final_price_egp,
                'status' => $job->status->value,
                'statusLabel' => $job->status->label(),
                'createdAt' => $job->created_at?->toIso8601String(),
            ]);

        return Inertia::render('jobs/index', [
            'jobs' => $jobs,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'type' => $filters['type'] ?? '',
                'customer' => $filters['customer'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
            'statuses' => JobStatus::options(),
            'types' => JobType::options(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Job $job): Response
    {
        $job->load([
            'customer', 'die', 'paperGrammage.paperType', 'paperGrammagePrice.supplier',
            'costLines', 'paperItems.paperGrammage.paperType', 'paperItems.paperGrammagePrice.supplier',
            'stages', 'pressAssignments.press', 'pressAssignments.assignedBy', 'odooSyncs',
        ]);

        $showRouting = in_array($job->status, [JobStatus::Approved, JobStatus::InProduction], true);

        return Inertia::render('jobs/show', [
            'job' => new JobDetailResource($job),
            'presses' => $showRouting ? PressResource::collection(Press::query()->orderBy('name')->get()) : [],
        ]);
    }
}
