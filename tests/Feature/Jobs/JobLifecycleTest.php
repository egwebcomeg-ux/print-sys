<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStageStatus;
use App\Enums\JobStatus;
use App\Jobs\SyncOdooInvoice;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\Press;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_draft_to_completed(): void
    {
        Queue::fake();
        $sales = User::factory()->create();
        $production = User::factory()->production()->create();
        $die = CuttingDie::factory()->create(['jobs_run_count' => 3]);
        $job = Job::factory()->create(['quantity' => 5000, 'die_id' => $die->id, 'is_using_existing_die' => true]);

        $this->actingAs($sales)->patch(route('jobs.status.update', $job), ['status' => 'quoted'])->assertRedirect();
        $this->actingAs($sales)->patch(route('jobs.status.update', $job), ['status' => 'approved'])->assertRedirect();

        $job->refresh();
        $this->assertSame(JobStatus::Approved, $job->status);
        $this->assertGreaterThan(0, $job->stages()->count(), 'approval seeds the default stages');
        $this->assertSame(4, $die->fresh()->jobs_run_count, 'approval counts a run on the die');

        $this->actingAs($production)->patch(route('jobs.status.update', $job), ['status' => 'in_production'])->assertRedirect();

        $stage = $job->stages()->first();
        $this->actingAs($production)->patch(route('jobs.stages.update', [$job, $stage]), ['status' => 'in_progress']);
        $this->assertSame(JobStageStatus::InProgress, $stage->fresh()->status);
        $this->assertNotNull($stage->fresh()->started_at);

        $this->actingAs($production)->post(route('jobs.complete', $job), ['produced_quantity' => 4870])->assertRedirect();

        $job->refresh();
        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertSame(4870, $job->produced_quantity);
        Queue::assertPushed(SyncOdooInvoice::class, 1);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $job = Job::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('jobs.status.update', $job), ['status' => 'approved'])
            ->assertSessionHasErrors('status');

        $this->assertSame(JobStatus::Draft, $job->fresh()->status);
    }

    public function test_invoiced_cannot_be_set_by_hand(): void
    {
        $job = Job::factory()->status(JobStatus::Completed)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('jobs.status.update', $job), ['status' => 'invoiced'])
            ->assertSessionHasErrors('status');
    }

    public function test_roles_per_transition(): void
    {
        $quoted = Job::factory()->status(JobStatus::Quoted)->create();
        $approved = Job::factory()->status(JobStatus::Approved)->create();

        $this->actingAs(User::factory()->production()->create())
            ->patch(route('jobs.status.update', $quoted), ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->patch(route('jobs.status.update', $approved), ['status' => 'in_production'])
            ->assertForbidden();
    }

    public function test_completion_requires_produced_quantity(): void
    {
        $job = Job::factory()->status(JobStatus::InProduction)->create();

        $this->actingAs(User::factory()->production()->create())
            ->post(route('jobs.complete', $job), [])
            ->assertSessionHasErrors('produced_quantity');

        $this->assertSame(JobStatus::InProduction, $job->fresh()->status);
    }

    public function test_press_assignment_is_manual_and_keeps_history(): void
    {
        $production = User::factory()->production()->create();
        $job = Job::factory()->status(JobStatus::Approved)->create();
        [$first, $second] = Press::factory()->count(2)->create();

        $this->actingAs($production)->post(route('jobs.press-assignments.store', $job), ['press_id' => $first->id])->assertRedirect();
        $this->actingAs($production)->post(route('jobs.press-assignments.store', $job), ['press_id' => $second->id])->assertRedirect();

        $this->assertSame(2, $job->pressAssignments()->count());
        $this->assertSame($second->id, $job->latestPressAssignment()->first()->press_id);
        $this->assertSame($production->id, $job->pressAssignments()->first()->assigned_by_user_id);
    }

    public function test_press_cannot_be_assigned_before_approval(): void
    {
        $job = Job::factory()->status(JobStatus::Quoted)->create();

        $this->actingAs(User::factory()->production()->create())
            ->post(route('jobs.press-assignments.store', $job), ['press_id' => Press::factory()->create()->id]);

        $this->assertSame(0, $job->pressAssignments()->count());
    }

    public function test_job_pages_render_for_every_status(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();
        Press::factory()->create();

        $this->actingAs($admin)->get(route('jobs.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('production.board'))->assertOk();

        foreach (JobStatus::cases() as $status) {
            $job = Job::factory()->status($status)->create();
            $this->actingAs($admin)->get(route('jobs.show', $job))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('jobs/show')->where('job.status', $status->value));
        }

        $manual = Job::factory()->manual()->create();
        $this->actingAs($admin)->get(route('jobs.show', $manual))->assertOk();
    }
}
