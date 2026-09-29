<?php

namespace Tests\Feature\Leads;

use App\Actions\Jobs\LinkLeadToJob;
use App\Enums\JobStatus;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Lead;
use App\Models\User;
use App\Services\Jobs\JobLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_crud(): void
    {
        $sales = User::factory()->create();

        $this->actingAs($sales)->get(route('leads.index'))->assertOk();
        $this->actingAs($sales)->get(route('leads.create'))->assertOk();
        $this->actingAs($sales)->post(route('leads.store'), [
            'contact_name' => 'أ. محمد',
            'company_name' => 'صيدليات النور',
            'phone' => '01000000000',
            'status' => 'new',
        ])->assertRedirect(route('leads.index'));

        $lead = Lead::query()->sole();
        $this->assertSame($sales->id, $lead->owner_user_id);
        $this->actingAs($sales)->get(route('leads.edit', $lead))->assertOk();
    }

    public function test_convert_creates_customer_and_opens_the_calculator(): void
    {
        $sales = User::factory()->create();
        $lead = Lead::factory()->create(['company_name' => 'صيدليات النور']);

        $response = $this->actingAs($sales)->post(route('leads.convert', $lead), ['type' => 'box']);

        $lead->refresh();
        $customer = Customer::query()->where('name', 'صيدليات النور')->sole();
        $this->assertSame($customer->id, $lead->customer_id);
        $response->assertRedirect(route('jobs.box.create', ['customer' => $customer->id, 'lead' => $lead->id]));
    }

    public function test_quoted_then_won_through_the_job_lifecycle(): void
    {
        $lead = Lead::factory()->create(['customer_id' => Customer::factory()->create()->id]);
        $job = Job::factory()->status(JobStatus::Quoted)->create(['customer_id' => $lead->customer_id]);

        app(LinkLeadToJob::class)->handle($lead->id, $job);
        $this->assertSame(LeadStatus::Quoted, $lead->fresh()->status);

        app(JobLifecycleService::class)->transition($job, JobStatus::Approved, User::factory()->create());
        $this->assertSame(LeadStatus::Won, $lead->fresh()->status);
    }

    public function test_production_role_has_no_access_to_leads(): void
    {
        $this->actingAs(User::factory()->production()->create())
            ->get(route('leads.index'))
            ->assertForbidden();
    }
}
