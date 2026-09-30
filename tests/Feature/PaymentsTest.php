<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Enums\OdooSyncStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Support\CustomerBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // company settings (14% VAT)
    }

    public function test_balance_uses_billed_amount_plus_vat_minus_payments(): void
    {
        $customer = Customer::factory()->create();
        $job = Job::factory()->status(JobStatus::Invoiced)->create(['customer_id' => $customer->id, 'final_price_egp' => 10000]);
        // Billed 9,500 (outside tolerance) — that's what counts, not the quote.
        $job->odooSyncs()->create(['status' => OdooSyncStatus::Success, 'response_payload' => ['billed_total' => 9500]]);
        // A quoted (not invoiced) job doesn't count.
        Job::factory()->status(JobStatus::Quoted)->create(['customer_id' => $customer->id, 'final_price_egp' => 5000]);

        $sales = User::factory()->create();
        $this->actingAs($sales)->post(route('customers.payments.store', $customer), [
            'amount_egp' => 4000, 'method' => 'bank_transfer', 'paid_at' => now()->toDateString(), 'job_id' => $job->id,
        ])->assertRedirect();

        $balance = CustomerBalance::forCustomer($customer->id);
        $this->assertEquals(10830, $balance['invoiced']); // 9500 × 1.14
        $this->assertEquals(4000, $balance['paid']);
        $this->assertEquals(6830, $balance['balance']);

        $this->actingAs($sales)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('customers/show')->where('balance.balance', 6830)->has('payments', 1));
        $this->actingAs($sales)->get(route('customers.index', ['search' => $customer->name]))
            ->assertInertia(fn ($page) => $page->where('customers.data.0.balance', 6830));
    }

    public function test_payment_job_must_belong_to_the_customer_and_date_not_in_future(): void
    {
        $customer = Customer::factory()->create();
        $otherJob = Job::factory()->create();
        $sales = User::factory()->create();

        $this->actingAs($sales)->post(route('customers.payments.store', $customer), [
            'amount_egp' => 100, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'job_id' => $otherJob->id,
        ])->assertSessionHasErrors('job_id');

        $this->actingAs($sales)->post(route('customers.payments.store', $customer), [
            'amount_egp' => 100, 'method' => 'cash', 'paid_at' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_at');
    }

    public function test_owed_balance_counts_toward_the_credit_warning(): void
    {
        $customer = Customer::factory()->create(['credit_limit_egp' => 10000]);
        Job::factory()->status(JobStatus::Invoiced)->create(['customer_id' => $customer->id, 'final_price_egp' => 8000]); // owes 9,120
        $job = Job::factory()->status(JobStatus::Quoted)->create(['customer_id' => $customer->id, 'final_price_egp' => 2000]);

        $this->actingAs(User::factory()->admin()->create())->get(route('jobs.show', $job))
            ->assertInertia(fn ($page) => $page->where('job.warnings.0.type', 'credit'));
    }

    public function test_production_cannot_record_payments(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->production()->create())
            ->post(route('customers.payments.store', $customer), ['amount_egp' => 1, 'method' => 'cash', 'paid_at' => now()->toDateString()])
            ->assertForbidden();
    }
}
