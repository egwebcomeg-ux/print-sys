<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\PaperGrammagePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBoxJobTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function quote(PaperGrammagePrice $price, array $overrides = []): array
    {
        $breakdown = [
            'paperCost' => 3000, 'platesCost' => 300, 'pressRunCost' => 200, 'laminationCost' => 150,
            'dieToolingCost' => 650, 'dieCuttingRunCost' => 100, 'gluingCost' => 250,
        ];
        $base = array_sum($breakdown); // 4650
        $margin = 20;
        $quantity = 5000;
        $unit = round($base * (1 + $margin / 100) / $quantity, 2);

        return array_replace([
            'customer_id' => Customer::factory()->create()->id,
            'boxType' => 'medicine',
            'shape' => 'reverse_tuck_end',
            'dimensions' => ['lengthCm' => 9, 'widthCm' => 5, 'depthCm' => 3],
            'quantity' => $quantity,
            'paperTypeName' => 'x',
            'gsm' => 300,
            'paperTypeId' => (string) $price->grammage->paper_type_id,
            'grammageId' => (string) $price->paper_grammage_id,
            'supplierPriceId' => (string) $price->id,
            'supplierName' => 'x',
            'pricePerTonEgp' => (float) $price->price_per_ton_egp,
            'printColors' => 2,
            'lamination' => 'matte',
            'isUsingExistingDie' => false,
            'dieId' => null,
            'rawSheetsNeeded' => 900,
            'upsPerRawSheet' => 6,
            'interlocked' => true,
            'marginPercent' => $margin,
            'marginAmountEgp' => $base * $margin / 100,
            'baseCostEgp' => $base,
            'unitPriceEgp' => $unit,
            'totalPriceEgp' => round($unit * $quantity, 2),
            'costBreakdown' => $breakdown,
        ], $overrides);
    }

    public function test_sales_can_confirm_a_box_quote_into_a_draft_job_with_cost_lines(): void
    {
        $sales = User::factory()->create();
        $price = PaperGrammagePrice::factory()->create();

        $response = $this->actingAs($sales)->post(route('jobs.box.store'), $this->quote($price));

        $job = Job::query()->sole();
        $response->assertRedirect(route('jobs.show', $job));

        $this->assertSame(JobType::Box, $job->job_type);
        $this->assertSame(JobStatus::Draft, $job->status);
        $this->assertSame($price->id, $job->paper_grammage_price_id);
        $this->assertEquals(4650, (float) $job->base_cost_egp);
        $this->assertEquals(20, (float) $job->margin_percent);
        $this->assertSame(900, $job->raw_sheets_needed);
        $this->assertSame(7, $job->costLines()->count());
        $this->assertEquals(4650, (float) $job->costLines()->sum('amount_egp'));
        $this->assertSame('medicine', $job->quote_snapshot['boxType']);
    }

    public function test_existing_die_is_stored_when_used(): void
    {
        $price = PaperGrammagePrice::factory()->create();
        $die = CuttingDie::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['isUsingExistingDie' => true, 'dieId' => (string) $die->id]))
            ->assertRedirect();

        $this->assertSame($die->id, Job::query()->sole()->die_id);
    }

    public function test_unsaved_local_paper_ids_are_rejected_with_a_clear_message(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['grammageId' => 'gsm-1727600000-1']))
            ->assertSessionHasErrors('grammageId');

        $this->assertSame(0, Job::query()->count());
    }

    public function test_grammage_must_belong_to_the_paper_type(): void
    {
        $price = PaperGrammagePrice::factory()->create();
        $other = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['paperTypeId' => (string) $other->grammage->paper_type_id]))
            ->assertSessionHasErrors('grammageId');
    }

    public function test_total_must_match_cost_plus_manual_margin(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['totalPriceEgp' => 999999]))
            ->assertSessionHasErrors('totalPriceEgp');

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['marginPercent' => -5]))
            ->assertSessionHasErrors('marginPercent');
    }

    public function test_production_role_cannot_create_quotes(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->production()->create())
            ->post(route('jobs.box.store'), $this->quote($price))
            ->assertForbidden();
    }

    public function test_create_pages_render_with_server_data(): void
    {
        $this->seed();
        $sales = User::query()->where('email', 'sales@pantopack.local')->sole();

        $this->actingAs($sales)->get(route('jobs.box.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('jobs/create-box')
                ->has('dies', 5)
                ->has('papers', 5)
                ->has('pastJobs', 2)
                ->where('papers.0.grammages.0.prices.0.pricePerTonEgp', fn ($v) => $v > 0)
                ->where('pricingConstants.spoilageRate', 0.03));

        $this->actingAs($sales)->get(route('jobs.manual.create'))->assertOk();
    }
}
