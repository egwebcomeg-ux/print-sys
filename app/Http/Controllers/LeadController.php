<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Simple CRM leads/opportunities (الفرص) that feed into job creation.
 */
class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $leads = Lead::query()
            ->with(['customer:id,name', 'owner:id,name'])
            ->when(in_array($status, LeadStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'contactName' => $lead->contact_name,
                'companyName' => $lead->company_name,
                'phone' => $lead->phone,
                'source' => $lead->source,
                'status' => $lead->status->value,
                'statusLabel' => $lead->status->label(),
                'expectedQuantity' => $lead->expected_quantity,
                'customer' => $lead->customer?->name,
                'owner' => $lead->owner?->name,
                'convertedJobId' => $lead->converted_job_id,
            ]);

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'statuses' => LeadStatus::options(),
            'filters' => ['status' => in_array($status, LeadStatus::values(), true) ? $status : ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('leads/form', ['lead' => null, ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Lead::query()->create($this->validated($request) + ['owner_user_id' => $request->user()->id]);
        $this->toast('تم إضافة الفرصة');

        return to_route('leads.index');
    }

    public function edit(Lead $lead): Response
    {
        return Inertia::render('leads/form', ['lead' => $lead, ...$this->options()]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($this->validated($request));
        $this->toast('تم حفظ الفرصة');

        return to_route('leads.index');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();
        $this->toast('تم مسح الفرصة');

        return to_route('leads.index');
    }

    /**
     * Turn a lead into a job: link or create the customer, then open the
     * chosen calculator with the customer and lead preselected.
     */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['box', 'manual'])]]);

        if (! $lead->customer_id) {
            $customer = Customer::query()->create([
                'name' => $lead->company_name ?: $lead->contact_name,
                'phone' => $lead->phone,
                'notes' => $lead->company_name ? "جهة الاتصال: {$lead->contact_name}" : null,
            ]);
            $lead->update(['customer_id' => $customer->id]);
        }

        $route = $data['type'] === 'box' ? 'jobs.box.create' : 'jobs.manual.create';

        return to_route($route, ['customer' => $lead->customer_id, 'lead' => $lead->id]);
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'statuses' => LeadStatus::options(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'expected_quantity' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }
}
