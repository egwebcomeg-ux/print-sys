<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Payment;
use App\Support\CustomerBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->withCount('jobs')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $balances = CustomerBalance::for($customers->getCollection()->map(fn (Customer $c): int => $c->id)->all());
        $customers->through(fn (Customer $c) => [...$c->toArray(), 'balance' => $balances[$c->id]['balance'] ?? 0]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    /** Customer account: balance, jobs and payments. */
    public function show(Customer $customer): Response
    {
        $customer->load(['payments.user:id,name', 'payments.job:id,title,box_type,length_cm,width_cm,depth_cm,job_type']);

        return Inertia::render('customers/show', [
            'customer' => $customer->only(['id', 'name', 'phone', 'email', 'credit_limit_egp', 'notes']),
            'balance' => CustomerBalance::forCustomer($customer->id),
            'jobs' => $customer->jobs()->latest('id')->get()->map(fn (Job $j) => [
                'id' => $j->id,
                'name' => $j->displayName(),
                'status' => $j->status->value,
                'statusLabel' => $j->status->label(),
                'finalPrice' => (float) $j->final_price_egp,
                'paid' => (float) $j->payments()->sum('amount_egp'),
                'createdAt' => $j->created_at?->toIso8601String(),
            ]),
            'payments' => $customer->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount_egp,
                'method' => $p->method->label(),
                'paidAt' => $p->paid_at->toDateString(),
                'reference' => $p->reference,
                'notes' => $p->notes,
                'job' => $p->job ? ['id' => $p->job->id, 'name' => $p->job->displayName()] : null,
                'by' => $p->user?->name,
            ]),
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/form', ['customer' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::query()->create($this->validated($request));
        $this->toast('تم إضافة العميل');

        // Quick-create from the job pages returns to where the user was.
        if ($request->boolean('stay')) {
            Inertia::flash('createdCustomerId', $customer->id);

            return back();
        }

        return to_route('customers.index');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/form', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));
        $this->toast('تم حفظ بيانات العميل');

        return to_route('customers.index');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($this->deleteSafely(fn () => $customer->delete(), 'مينفعش تمسح عميل عليه شغلانات')) {
            $this->toast('تم مسح العميل');
        }

        return to_route('customers.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\s-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'credit_limit_egp' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['phone.regex' => 'التليفون أرقام بس']);
    }
}
