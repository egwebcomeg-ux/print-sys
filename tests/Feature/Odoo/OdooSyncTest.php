<?php

namespace Tests\Feature\Odoo;

use App\Enums\JobStatus;
use App\Enums\OdooSyncStatus;
use App\Jobs\SyncOdooInvoice;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Services\Odoo\Exceptions\OdooRejectedException;
use App\Services\Odoo\Exceptions\OdooTransientException;
use App\Services\Odoo\FakeOdooClient;
use App\Services\Odoo\OdooClientInterface;
use App\Services\Odoo\OdooInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OdooSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeOdooClient $odoo;

    protected function setUp(): void
    {
        parent::setUp();

        config(['odoo.fake' => true, 'odoo.auto_post' => true, 'odoo.tax_id' => 7]);
        $this->odoo = new FakeOdooClient;
        $this->app->instance(OdooClientInterface::class, $this->odoo);
    }

    private function completedJob(array $attributes = []): Job
    {
        return Job::factory()->status(JobStatus::Completed)->create($attributes + [
            'quantity' => 5000,
            'produced_quantity' => 4800,
            'final_price_egp' => 12000,
        ]);
    }

    public function test_invoice_uses_produced_quantity_and_marks_job_invoiced(): void
    {
        $job = $this->completedJob();

        SyncOdooInvoice::dispatchSync($job);

        $job->refresh();
        $this->assertSame(JobStatus::Invoiced, $job->status);

        $sync = $job->odooSyncs()->sole();
        $this->assertSame(OdooSyncStatus::Success, $sync->status);
        $this->assertNotNull($sync->odoo_invoice_id);
        $this->assertStringStartsWith('INV/', $sync->response_payload['name']);

        $line = $sync->request_payload['invoice_line_ids'][0][2];
        $this->assertSame(4800, $line['quantity']);               // produced, not quoted
        $this->assertEquals(2.4, $line['price_unit']);            // 12000 / 5000, 2 dp like Odoo
        $this->assertEquals(11520, $sync->response_payload['billed_total']); // 4800 × 2.4
        $this->assertSame([[6, 0, [7]]], $line['tax_ids']);
        $this->assertSame("PP-{$job->id}", $sync->request_payload['ref']);
        $this->assertNotNull($job->customer->fresh()->odoo_partner_id);
    }

    public function test_transient_failure_is_logged_and_rethrown_for_retry(): void
    {
        $job = $this->completedJob();
        $this->odoo->failNextWith = new OdooTransientException('timeout');

        try {
            app(OdooInvoiceService::class)->invoice($job);
            $this->fail('expected exception');
        } catch (OdooTransientException) {
            // the queue retries on this
        }

        $sync = $job->odooSyncs()->sole();
        $this->assertSame(OdooSyncStatus::Failed, $sync->status);
        $this->assertSame('timeout', $sync->error_message);
        $this->assertSame(JobStatus::Completed, $job->fresh()->status, 'a failed call never undoes completion');
    }

    public function test_rejection_fails_the_queued_job_without_retrying(): void
    {
        $job = $this->completedJob();
        $this->odoo->failNextWith = new OdooRejectedException('Invalid partner');

        SyncOdooInvoice::dispatchSync($job);

        $this->assertSame(OdooSyncStatus::Failed, $job->odooSyncs()->sole()->status);
        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
    }

    public function test_retry_reuses_an_invoice_odoo_already_created(): void
    {
        $job = $this->completedJob();
        $service = app(OdooInvoiceService::class);

        // First attempt creates the move, then fails before finishing.
        $payload = $service->payload($job);
        $moveId = $this->odoo->executeKw('account.move', 'create', [$payload]);

        $service->invoice($job->fresh());

        $creates = collect($this->odoo->calls)->where('model', 'account.move')->where('method', 'create');
        $this->assertCount(1, $creates, 'no duplicate invoice');
        $this->assertSame((string) $moveId, $job->odooSyncs()->first()->odoo_invoice_id);
        $this->assertTrue($job->odooSyncs()->first()->response_payload['reused']);
    }

    public function test_existing_partner_is_reused_by_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'صيدلية العزبي']);
        $partnerId = $this->odoo->executeKw('res.partner', 'create', [['name' => 'صيدلية العزبي']]);

        SyncOdooInvoice::dispatchSync($this->completedJob(['customer_id' => $customer->id]));

        $this->assertSame((string) $partnerId, $customer->fresh()->odoo_partner_id);
    }

    public function test_manual_fallback_and_retry_endpoints(): void
    {
        $sales = User::factory()->create();
        $job = $this->completedJob();

        $this->actingAs($sales)
            ->post(route('jobs.odoo-syncs.manual', $job), ['odoo_invoice_id' => 'INV/2026/00042'])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobStatus::Invoiced, $job->status);
        $this->assertTrue($job->odooSyncs()->sole()->isManual());

        // Once invoiced, neither path runs again.
        $this->actingAs($sales)->post(route('jobs.odoo-syncs.store', $job))->assertRedirect();
        $this->assertSame(1, $job->odooSyncs()->count());
    }

    public function test_production_role_cannot_touch_invoicing(): void
    {
        $job = $this->completedJob();

        $this->actingAs(User::factory()->production()->create())
            ->post(route('jobs.odoo-syncs.manual', $job), ['odoo_invoice_id' => 'X'])
            ->assertForbidden();
    }

    public function test_lump_sum_manual_job_is_billed_as_one_line(): void
    {
        $job = Job::factory()->manual()->status(JobStatus::Completed)->create([
            'quantity' => null, 'produced_quantity' => 300, 'final_price_egp' => 9000,
        ]);

        $line = app(OdooInvoiceService::class)->payload($job)['invoice_line_ids'][0][2];

        $this->assertSame(1, $line['quantity']);
        $this->assertEquals(9000, $line['price_unit']);
    }
}
