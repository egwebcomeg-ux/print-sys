<?php

namespace App\Http\Controllers\Jobs;

use App\Actions\Jobs\CreateBoxJob;
use App\Actions\Jobs\CreateManualJob;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreBoxJobRequest;
use App\Http\Requests\Jobs\StoreManualJobRequest;
use App\Http\Resources\DieResource;
use App\Http\Resources\PaperTypeResource;
use App\Http\Resources\SavedJobSpecResource;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Edit / re-price a job that hasn't been approved yet. The calculator
 * reopens with the saved spec and today's prices; confirming overwrites
 * the job in place and puts it back to draft (the quote changed).
 */
class JobEditController extends Controller
{
    public function edit(Job $job): Response|RedirectResponse
    {
        if (! self::editable($job)) {
            $this->toast('الشغلانة اتوافق عليها — مينفعش تتعدل', 'error');

            return to_route('jobs.show', $job);
        }

        $editing = ['id' => $job->id, 'name' => $job->displayName(), 'customerId' => $job->customer_id];
        $common = [
            'papers' => PaperTypeResource::collection(BoxJobController::paperCatalogue()),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'defaultMarginPercent' => Settings::defaultMarginPercent(),
            'preselect' => ['customerId' => $job->customer_id, 'leadId' => null],
            'editing' => $editing,
        ];

        if ($job->job_type === JobType::Manual) {
            $snapshot = $job->quote_snapshot ?? [];

            return Inertia::render('jobs/create-manual', $common + [
                'initial' => [
                    'title' => $job->title,
                    'lineItems' => $snapshot['lineItems'] ?? [],
                    'costLines' => $snapshot['costLines'] ?? [],
                    'marginPercent' => (float) $job->margin_percent,
                    'producedQuantity' => $job->quantity,
                ],
            ]);
        }

        $job->load(['customer', 'paperGrammage']);

        return Inertia::render('jobs/create-box', $common + [
            'dies' => DieResource::collection(CuttingDie::query()->orderBy('code')->get()),
            'pastJobs' => [],
            'pricingConstants' => Settings::pricingConstants(),
            'initial' => [
                'title' => $job->title,
                'job' => new SavedJobSpecResource($job),
                'marginPercent' => (float) $job->margin_percent,
            ],
        ]);
    }

    public function updateBox(StoreBoxJobRequest $request, Job $job, CreateBoxJob $createBoxJob): RedirectResponse
    {
        abort_unless(self::editable($job) && $job->job_type === JobType::Box, 403);

        $old = (float) $job->final_price_egp;
        $createBoxJob->handle($request->validated(), $request->pricing(), $job);
        self::logRepriced($job, $old);
        $this->toast("تم إعادة تسعير الشغلانة #{$job->id}");

        return to_route('jobs.show', $job);
    }

    public function updateManual(StoreManualJobRequest $request, Job $job, CreateManualJob $createManualJob): RedirectResponse
    {
        abort_unless(self::editable($job) && $job->job_type === JobType::Manual, 403);

        $old = (float) $job->final_price_egp;
        $createManualJob->handle($request->validated(), $job);
        self::logRepriced($job, $old);
        $this->toast("تم إعادة تسعير الشغلانة #{$job->id}");

        return to_route('jobs.show', $job);
    }

    private static function logRepriced(Job $job, float $old): void
    {
        ActivityLog::record('job', $job->id, 'repriced', sprintf('إعادة تسعير: %s ← %s ج', number_format($old, 2), number_format((float) $job->fresh()->final_price_egp, 2)));
    }

    public static function editable(Job $job): bool
    {
        return in_array($job->status, [JobStatus::Draft, JobStatus::Quoted], true);
    }
}
