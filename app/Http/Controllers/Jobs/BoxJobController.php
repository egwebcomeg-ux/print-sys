<?php

namespace App\Http\Controllers\Jobs;

use App\Actions\Jobs\CreateBoxJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreBoxJobRequest;
use App\Http\Resources\DieResource;
use App\Http\Resources\PaperTypeResource;
use App\Http\Resources\SavedJobSpecResource;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\Lead;
use App\Models\PaperType;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoxJobController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('jobs/create-box', [
            'dies' => DieResource::collection(CuttingDie::query()->orderBy('code')->get()),
            'papers' => PaperTypeResource::collection(self::paperCatalogue()),
            'pastJobs' => SavedJobSpecResource::collection(
                Job::query()->box()->with(['customer', 'paperGrammage'])->latest('id')->limit(15)->get()
            ),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'pricingConstants' => Settings::pricingConstants(),
            'defaultMarginPercent' => Settings::defaultMarginPercent(),
            'preselect' => self::preselect($request),
        ]);
    }

    public function store(StoreBoxJobRequest $request, CreateBoxJob $createBoxJob): RedirectResponse
    {
        $job = $createBoxJob->handle($request->validated());
        $this->toast("تم تسجيل الشغلانة #{$job->id} كمسودة");

        return to_route('jobs.show', $job);
    }

    /** @return Collection<int, PaperType> */
    public static function paperCatalogue()
    {
        return PaperType::query()->with('grammages.prices.supplier')->orderBy('name')->get();
    }

    /**
     * Customer/lead to preselect when coming from a lead ("حوّل لشغلانة").
     *
     * @return array{customerId: int|null, leadId: int|null}
     */
    public static function preselect(Request $request): array
    {
        $lead = $request->integer('lead') ? Lead::query()->find($request->integer('lead')) : null;

        return [
            'customerId' => $request->integer('customer') ?: $lead?->customer_id,
            'leadId' => $lead?->id,
        ];
    }
}
