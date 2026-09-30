<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use App\Services\Jobs\JobStageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_prints_with_a_qr_to_the_scan_page(): void
    {
        $job = Job::factory()->status(JobStatus::Approved)->create();
        JobStageTemplate::seedFor($job);

        $this->actingAs(User::factory()->production()->create())
            ->get(route('jobs.work-order', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('print/work-order')
                ->where('order.number', sprintf('WO-%05d', $job->id))
                ->has('order.stages', count(config('pantopack.default_stages.default')))
                ->where('qrSvg', fn ($svg) => str_contains($svg, '<svg')));
    }

    public function test_scan_page_lets_production_advance_the_current_stage(): void
    {
        $production = User::factory()->production()->create();
        $job = Job::factory()->status(JobStatus::InProduction)->create();
        JobStageTemplate::seedFor($job);
        $stage = $job->stages()->first();

        $this->actingAs($production)->get(route('jobs.scan', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('jobs/scan')->where('job.canUpdateStages', true));

        $this->actingAs($production)
            ->patch(route('jobs.stages.update', [$job, $stage]), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame('in_progress', $stage->fresh()->status->value);
    }

    public function test_scan_requires_login(): void
    {
        $job = Job::factory()->create();

        $this->get(route('jobs.scan', $job))->assertRedirect(route('login'));
    }
}
