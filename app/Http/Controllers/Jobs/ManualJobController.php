<?php

namespace App\Http\Controllers\Jobs;

use App\Actions\Jobs\CreateManualJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreManualJobRequest;
use App\Http\Resources\PaperTypeResource;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualJobController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('jobs/create-manual', [
            'papers' => PaperTypeResource::collection(BoxJobController::paperCatalogue()),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'defaultMarginPercent' => Settings::defaultMarginPercent(),
            'preselect' => BoxJobController::preselect($request),
        ]);
    }

    public function store(StoreManualJobRequest $request, CreateManualJob $createManualJob): RedirectResponse
    {
        $job = $createManualJob->handle($request->validated());
        ActivityLog::record('job', $job->id, 'created', 'اتسجلت بسعر '.number_format((float) $job->final_price_egp, 2).' ج');
        $this->toast("تم تسجيل الشغلانة #{$job->id} كمسودة");

        return to_route('jobs.show', $job);
    }
}
