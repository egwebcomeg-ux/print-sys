<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\PaperGrammage;
use App\Models\PaperStock;
use App\Models\User;
use App\Notifications\JobNotification;
use App\Services\Inventory\PaperInventory;
use App\Services\Jobs\JobLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function boxJob(PaperGrammage $grammage, int $sheets, JobStatus $status = JobStatus::Quoted): Job
    {
        return Job::factory()->box()->status($status)->create([
            'paper_grammage_id' => $grammage->id,
            'raw_sheets_needed' => $sheets,
            // Stored landscape on purpose: stock matches regardless of orientation.
            'quote_snapshot' => ['pricing' => ['sheetWidthCm' => 100, 'sheetHeightCm' => 70]],
        ]);
    }

    public function test_approving_a_job_deducts_tracked_paper_once_and_alerts_when_low(): void
    {
        Notification::fake();
        $grammage = PaperGrammage::factory()->create();
        $admin = User::factory()->admin()->create();
        $production = User::factory()->production()->create();

        $this->actingAs($admin)->post(route('inventory.store'), [
            'paper_grammage_id' => $grammage->id, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100,
            'quantity_sheets' => 1000, 'reorder_level' => 500,
        ])->assertRedirect();
        $stock = PaperStock::sole();

        $job = $this->boxJob($grammage, 600);
        app(JobLifecycleService::class)->transition($job, JobStatus::Approved, $admin);

        $this->assertSame(400, $stock->fresh()->quantity_sheets);
        $this->assertDatabaseHas('stock_movements', ['job_id' => $job->id, 'type' => 'consumption', 'quantity' => -600, 'balance_after' => 400]);
        Notification::assertSentTo($production, JobNotification::class);

        // Idempotent: a second call never double-deducts.
        PaperInventory::consumeFor($job, $admin);
        $this->assertSame(400, $stock->fresh()->quantity_sheets);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('lowStock', 1)->where('lowStock.0.quantity', 400));
    }

    public function test_untracked_paper_is_not_deducted_and_manual_items_are(): void
    {
        $tracked = PaperGrammage::factory()->create();
        $untracked = PaperGrammage::factory()->create();
        $stock = PaperStock::create(['paper_grammage_id' => $tracked->id, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100, 'quantity_sheets' => 300]);

        $job = Job::factory()->manual()->status(JobStatus::Quoted)->create();
        foreach ([[$tracked, 120], [$tracked, 30], [$untracked, 999]] as $i => [$g, $n]) {
            $job->paperItems()->create([
                'label' => "بند {$i}", 'paper_grammage_id' => $g->id, 'sheet_width_cm' => 100, 'sheet_height_cm' => 70,
                'sheets_count' => $n, 'weight_kg' => 1, 'cost_egp' => 1, 'sort_order' => $i,
            ]);
        }

        app(JobLifecycleService::class)->transition($job, JobStatus::Approved, User::factory()->admin()->create());

        $this->assertSame(150, $stock->fresh()->quantity_sheets);
        $this->assertSame(1, PaperStock::count());
    }

    public function test_shortage_warning_before_approval(): void
    {
        $grammage = PaperGrammage::factory()->create();
        PaperStock::create(['paper_grammage_id' => $grammage->id, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100, 'quantity_sheets' => 100]);
        $job = $this->boxJob($grammage, 250);

        $this->actingAs(User::factory()->create())->get(route('jobs.show', $job))
            ->assertInertia(fn ($page) => $page->where('job.warnings', fn ($w) => collect($w)->contains('type', 'stock')));
    }

    public function test_receipt_and_stock_count_adjustment(): void
    {
        $grammage = PaperGrammage::factory()->create();
        $stock = PaperStock::create(['paper_grammage_id' => $grammage->id, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100, 'quantity_sheets' => 50]);
        $production = User::factory()->production()->create();

        $this->actingAs($production)->post(route('inventory.receive', $stock), ['quantity' => 500])->assertRedirect();
        $this->actingAs($production)->post(route('inventory.adjust', $stock), ['counted' => 520, 'note' => 'جرد'])->assertRedirect();
        $this->actingAs($production)->post(route('inventory.adjust', $stock), ['counted' => 10])->assertSessionHasErrors('note');

        $this->assertSame(520, $stock->fresh()->quantity_sheets);
        $this->assertDatabaseHas('stock_movements', ['paper_stock_id' => $stock->id, 'type' => 'adjustment', 'quantity' => -30, 'balance_after' => 520]);

        $this->actingAs($production)->get(route('inventory.show', $stock))
            ->assertInertia(fn ($page) => $page->component('inventory/show')->has('movements.data', 2));
    }

    public function test_duplicate_items_rejected_and_sales_cannot_manage(): void
    {
        $grammage = PaperGrammage::factory()->create();
        PaperStock::create(['paper_grammage_id' => $grammage->id, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100]);
        $payload = ['paper_grammage_id' => $grammage->id, 'sheet_width_cm' => 100, 'sheet_height_cm' => 70, 'quantity_sheets' => 0];

        $this->actingAs(User::factory()->admin()->create())->post(route('inventory.store'), $payload)
            ->assertSessionHasErrors('paper_grammage_id');

        $sales = User::factory()->create();
        $this->actingAs($sales)->post(route('inventory.store'), $payload)->assertForbidden();
        $this->actingAs($sales)->get(route('inventory.index'))->assertOk();
    }
}
