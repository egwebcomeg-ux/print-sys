<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PaperGrammagePrice;
use App\Models\PaperPriceChange;
use App\Models\Press;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActivityAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_logged_and_notifies_production_but_not_the_actor(): void
    {
        Queue::fake();
        $sales = User::factory()->create();
        $production = User::factory()->production()->create();
        $job = Job::factory()->status(JobStatus::Quoted)->create();

        $this->actingAs($sales)->patch(route('jobs.status.update', $job), ['status' => 'approved']);

        $this->assertDatabaseHas('activity_logs', ['subject_type' => 'job', 'subject_id' => $job->id, 'action' => 'status', 'user_id' => $sales->id]);
        $this->assertSame(1, $production->notifications()->count());
        $this->assertSame(0, $sales->notifications()->count());

        // Bell endpoint + open marks read and redirects to the job.
        $this->actingAs($production)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('items.0.url', "/jobs/{$job->id}");
        $id = $production->notifications()->first()->id;
        $this->actingAs($production)->get(route('notifications.open', $id))->assertRedirect("/jobs/{$job->id}");
        $this->assertSame(0, $production->unreadNotifications()->count());
    }

    public function test_press_assignment_is_logged(): void
    {
        $job = Job::factory()->status(JobStatus::Approved)->create();
        $press = Press::factory()->create(['name' => 'مطبعة س']);

        $this->actingAs(User::factory()->production()->create())
            ->post(route('jobs.press-assignments.store', $job), ['press_id' => $press->id]);

        $this->assertSame('اتوزعت على مطبعة س', ActivityLog::query()->where('action', 'press')->value('description'));
    }

    public function test_price_changes_are_recorded_and_flag_open_quotes(): void
    {
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);
        $job = Job::factory()->status(JobStatus::Quoted)->create([
            'paper_grammage_id' => $price->paper_grammage_id,
            'paper_grammage_price_id' => $price->id,
            'updated_at' => now()->subDay(),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('grammages.prices.store', $price->paper_grammage_id), [
            'paper_supplier_id' => $price->paper_supplier_id, 'price_per_ton_egp' => 15000,
        ]);
        // Same price again → no new history row.
        $this->actingAs($admin)->post(route('grammages.prices.store', $price->paper_grammage_id), [
            'paper_supplier_id' => $price->paper_supplier_id, 'price_per_ton_egp' => 15000,
        ]);

        $change = PaperPriceChange::query()->sole();
        $this->assertEquals(14000, (float) $change->old_price_egp);
        $this->assertEquals(15000, (float) $change->new_price_egp);

        $this->actingAs($admin)->get(route('jobs.show', $job))
            ->assertInertia(fn ($page) => $page->where('job.warnings.0.type', 'price'));
    }

    public function test_credit_limit_warning(): void
    {
        $customer = Customer::factory()->create(['credit_limit_egp' => 10000]);
        Job::factory()->status(JobStatus::InProduction)->create(['customer_id' => $customer->id, 'final_price_egp' => 8000]);
        $job = Job::factory()->status(JobStatus::Quoted)->create(['customer_id' => $customer->id, 'final_price_egp' => 5000]);

        $this->actingAs(User::factory()->admin()->create())->get(route('jobs.show', $job))
            ->assertInertia(fn ($page) => $page->where('job.warnings.0.type', 'credit'));
    }

    public function test_reports_and_activity_pages_are_admin_only(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('reports'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('reports/index')->has('summary')->has('waste'));
        $this->actingAs($admin)->get(route('reports', ['month' => '2026-01']))->assertOk();
        $this->actingAs($admin)->get(route('activity-log'))->assertOk();

        $this->actingAs(User::factory()->create())->get(route('reports'))->assertForbidden();
    }
}
