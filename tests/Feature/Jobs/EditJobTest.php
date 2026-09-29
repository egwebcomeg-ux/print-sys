<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PaperGrammagePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_quoted_manual_job_can_be_repriced_in_place_and_returns_to_draft(): void
    {
        $sales = User::factory()->create();
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);
        $price->grammage->update(['gsm' => 300]);
        $job = Job::factory()->manual()->status(JobStatus::Quoted)->create([
            'title' => 'كتيب قديم',
            'margin_percent' => 10,
            'quote_snapshot' => ['lineItems' => [], 'costLines' => [], 'marginPercent' => 10, 'producedQuantity' => null],
        ]);
        $job->costLines()->create(['label' => 'x', 'amount_egp' => 5]);

        $this->actingAs($sales)->get(route('jobs.edit', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('jobs/create-manual')
                ->where('editing.id', $job->id)
                ->where('initial.title', 'كتيب قديم')
                ->where('initial.marginPercent', 10));

        // 70×100 @ 300 gsm, 100 sheets = 21 kg → 294 EGP; + 100 costs = 394; × 1.25 = 492.5
        $this->actingAs($sales)->put(route('jobs.manual.update', $job), [
            'customer_id' => Customer::factory()->create()->id,
            'title' => 'كتيب جديد',
            'lineItems' => [[
                'label' => 'متن',
                'paperTypeId' => (string) $price->grammage->paper_type_id,
                'grammageId' => (string) $price->paper_grammage_id,
                'supplierPriceId' => (string) $price->id,
                'sheetWidthCm' => 70, 'sheetHeightCm' => 100, 'sheetsCount' => 100,
            ]],
            'costLines' => [['label' => 'طباعة', 'amountEgp' => 100]],
            'marginPercent' => 25,
            'producedQuantity' => 500,
            'finalPriceEgp' => 492.5,
        ])->assertRedirect(route('jobs.show', $job));

        $job->refresh();
        $this->assertSame(JobStatus::Draft, $job->status, 'a re-priced quote must be re-sent');
        $this->assertSame('كتيب جديد', $job->title);
        $this->assertEquals(492.5, (float) $job->final_price_egp);
        $this->assertSame(1, $job->paperItems()->count());
        $this->assertEqualsCanonicalizing(['ورق', 'طباعة'], $job->costLines()->pluck('label')->all(), 'old cost lines are replaced');
        $this->assertSame(1, Job::query()->count(), 'edited in place, not duplicated');
    }

    public function test_approved_jobs_cannot_be_edited(): void
    {
        $job = Job::factory()->status(JobStatus::Approved)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('jobs.edit', $job))
            ->assertRedirect(route('jobs.show', $job));

        $this->actingAs(User::factory()->create())
            ->put(route('jobs.manual.update', $job), [])
            ->assertForbidden();
    }

    public function test_production_role_cannot_edit_jobs(): void
    {
        $job = Job::factory()->create();

        $this->actingAs(User::factory()->production()->create())
            ->get(route('jobs.edit', $job))
            ->assertForbidden();
    }
}
