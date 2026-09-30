<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\PaperGrammagePrice;
use App\Models\User;
use App\Services\Pricing\BoxPricer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBoxJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A BoxQuote payload whose numbers match what BoxPricer will compute
     * (the request rejects anything else). 9×5×3 reverse-tuck box on the
     * factory grammage (70×100 sheet), flat dieline 299×80 mm.
     *
     * @return array<string, mixed>
     */
    private function quote(PaperGrammagePrice $price, array $overrides = []): array
    {
        $inputs = [
            'boxType' => 'medicine',
            'shape' => 'reverse_tuck_end',
            'dimensions' => ['lengthCm' => 9, 'widthCm' => 5, 'depthCm' => 3],
            'flatWidthMm' => 299,
            'flatHeightMm' => 80,
            'interlockPitchMm' => 68, // the old 0.85 ratio on an 80 mm flat
            'piecesPerBox' => 1,
            'quantity' => 3000,
            'printColors' => 2,
            'lamination' => 'matte',
            'isUsingExistingDie' => false,
            'dieId' => null,
            'interlockEnabled' => true,
            'marginPercent' => 20,
        ];
        $inputs = array_replace($inputs, array_intersect_key($overrides, $inputs));

        $die = ! empty($overrides['dieId']) ? CuttingDie::query()->find($overrides['dieId']) : null;
        $pricing = app(BoxPricer::class)->price($inputs, $price->grammage, $price, $die);

        return array_replace($inputs, [
            'customer_id' => Customer::factory()->create()->id,
            'sheetWidthCm' => $pricing['sheetWidthCm'] ?? 70,
            'sheetHeightCm' => $pricing['sheetHeightCm'] ?? 100,
            'cutFraction' => $pricing['cutFraction'] ?? '1/2',
            'paperTypeName' => 'x',
            'gsm' => $price->grammage->gsm,
            'paperTypeId' => (string) $price->grammage->paper_type_id,
            'grammageId' => (string) $price->paper_grammage_id,
            'supplierPriceId' => (string) $price->id,
            'supplierName' => 'x',
            'pricePerTonEgp' => (float) $price->price_per_ton_egp,
            'rawSheetsNeeded' => $pricing['rawSheetsNeeded'],
            'upsPerRawSheet' => $pricing['upsPerRawSheet'],
            'interlocked' => $pricing['interlocked'],
            'marginAmountEgp' => $pricing['marginAmountEgp'],
            'baseCostEgp' => $pricing['baseCostEgp'],
            'unitPriceEgp' => $pricing['unitPriceEgp'],
            'totalPriceEgp' => $pricing['totalPriceEgp'],
            'costBreakdown' => $pricing['costBreakdown'],
        ], $overrides);
    }

    public function test_sales_can_confirm_a_box_quote_into_a_draft_job_with_server_priced_cost_lines(): void
    {
        $sales = User::factory()->create();
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);
        $price->grammage->update(['gsm' => 300]);
        $price = $price->fresh();

        $response = $this->actingAs($sales)->post(route('jobs.box.store'), $this->quote($price));

        $job = Job::query()->sole();
        $response->assertRedirect(route('jobs.show', $job));

        $this->assertSame(JobType::Box, $job->job_type);
        $this->assertSame(JobStatus::Draft, $job->status);
        $this->assertSame($price->id, $job->paper_grammage_price_id);

        // Worked example: 70×100 @ 300 gsm @ 14,000/ton = 2.94/sheet.
        // Half sheet usable 690×488: grid 12 ups, interlocked 14 → 14 × 2 = 28 per raw sheet.
        // The plan picks the FULL 70×100 sheet (2 cols × 14 nested rows = 28, same paper as
        // the half sheet but half the machine passes): ⌈3000/28 × 1.03⌉ = 111 sheets →
        // paper 326.34 + plates 300 + press 2×180×0.111 = 39.96 + matte 111×0.35 = 38.85
        // + new die 650 + die-cut 111×0.15 = 16.65 + glue 150 = 1521.80.
        $this->assertSame(111, $job->raw_sheets_needed);
        $this->assertSame(28, $job->ups_per_raw_sheet);
        $this->assertTrue($job->interlocked);
        $this->assertSame('1/1', $job->quote_snapshot['pricing']['cutFraction']);
        $this->assertEquals(1521.80, (float) $job->base_cost_egp);
        $this->assertEquals(20, (float) $job->margin_percent);
        $this->assertEquals(1830.00, (float) $job->final_price_egp); // 1826.16 / 3000 → unit 0.61 × 3000
        $this->assertSame(7, $job->costLines()->count());
        $this->assertEquals(1521.80, (float) $job->costLines()->sum('amount_egp'));
        $this->assertSame('medicine', $job->quote_snapshot['boxType']);
        $this->assertArrayNotHasKey('customer_id', $job->quote_snapshot);
    }

    public function test_lid_and_base_box_prices_two_pieces_per_box_on_the_cheapest_standard_sheet(): void
    {
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);

        // Dielines B/C: lid flat 312×346 (+bleed → 316×350), two pieces per box.
        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, [
                'shape' => 'lid_and_base', 'flatWidthMm' => 316, 'flatHeightMm' => 350,
                'interlockPitchMm' => null, 'piecesPerBox' => 2, 'quantity' => 1000,
            ]) + ['doubleWallOn' => 'width'])
            ->assertRedirect();

        $job = Job::query()->sole();
        $plan = $job->quote_snapshot['pricing'];
        $this->assertSame(2, $plan['piecesPerBox']);
        $this->assertSame(0, $plan['upsPerCutSheet'] % 2, 'whole lid+base sets per sheet');
        // 2,000 pieces (+3% spoilage) over the chosen sheet's ups.
        $this->assertSame((int) ceil(2000 / $job->ups_per_raw_sheet * 1.03), $job->raw_sheets_needed);
        $this->assertContains([$plan['sheetWidthCm'], $plan['sheetHeightCm']], [[70, 100], [88, 119]]);
        $this->assertSame('width', $job->quote_snapshot['doubleWallOn'], 'flute-direction choice is kept for repeats');
    }

    public function test_micro_flute_phone_and_pizza_boxes_have_no_gluing_cost(): void
    {
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);

        // Phone box 25×10×6 → 511×381 (+bleed); pizza 34×34×4 → 420×840.5 (+bleed).
        foreach ([['phone', 'phone_box', 515, 385], ['pizza', 'pizza_box', 424, 845], ['cake', 'self_lock_tray_lid', 604, 384]] as [$type, $shape, $w, $h]) {
            $this->actingAs(User::factory()->create())
                ->post(route('jobs.box.store'), $this->quote($price, [
                    'boxType' => $type, 'shape' => $shape, 'flatWidthMm' => $w, 'flatHeightMm' => $h,
                    'interlockPitchMm' => null, 'interlockEnabled' => false, 'quantity' => 1000,
                ]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $job = Job::query()->latest('id')->firstOrFail();
            $this->assertSame($shape, $job->box_shape->value);
            $this->assertSame(0.0, (float) $job->quote_snapshot['pricing']['costBreakdown']['gluingCost']);
            $this->assertFalse($job->costLines()->where('label', 'لصق وتطبيق')->exists());
        }
    }

    public function test_existing_die_is_stored_and_its_ups_drive_the_price(): void
    {
        $price = PaperGrammagePrice::factory()->create();
        $die = CuttingDie::factory()->create(['ups_on_cut_sheet' => 6, 'cut_fraction' => '1/2']);

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['isUsingExistingDie' => true, 'dieId' => (string) $die->id]))
            ->assertRedirect();

        $job = Job::query()->sole();
        $this->assertSame($die->id, $job->die_id);
        $this->assertSame(12, $job->ups_per_raw_sheet);
        $this->assertFalse($job->interlocked);
        $this->assertNull($job->costLines()->where('label', 'اسطمبة جديدة')->first());
    }

    public function test_tampered_prices_are_rejected(): void
    {
        $price = PaperGrammagePrice::factory()->create();
        $user = User::factory()->create();

        // Under-pricing from the browser (DevTools): internally consistent, but not the server's number.
        $cheap = $this->quote($price);
        $cheap['costBreakdown']['paperCost'] = 1;
        $cheap['baseCostEgp'] = array_sum($cheap['costBreakdown']);
        $cheap['unitPriceEgp'] = round($cheap['baseCostEgp'] * 1.2 / 3000, 2);
        $cheap['totalPriceEgp'] = round($cheap['unitPriceEgp'] * 3000, 2);
        $this->actingAs($user)->post(route('jobs.box.store'), $cheap)->assertSessionHasErrors('totalPriceEgp');

        $this->actingAs($user)
            ->post(route('jobs.box.store'), $this->quote($price, ['totalPriceEgp' => 999999]))
            ->assertSessionHasErrors('totalPriceEgp');

        $this->assertSame(0, Job::query()->count());
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

    public function test_paper_without_a_supplier_price_cannot_be_quoted(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['supplierPriceId' => null]))
            ->assertSessionHasErrors('supplierPriceId');
    }

    public function test_box_too_big_for_a_half_sheet_is_priced_on_the_full_sheet(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        // 715×300 mm flat (a 20×15×8 candy box): no fit on any half sheet. Full 70×100 takes 2
        // (rotated), full 88×119 takes 3 — and 3 on the bigger sheet is marginally less paper per box.
        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['flatWidthMm' => 715, 'flatHeightMm' => 300, 'interlockPitchMm' => null]))
            ->assertRedirect();

        $job = Job::query()->sole();
        $this->assertSame(3, $job->ups_per_raw_sheet);
        $this->assertSame([88, 119, '1/1'], [$job->quote_snapshot['pricing']['sheetWidthCm'], $job->quote_snapshot['pricing']['sheetHeightCm'], $job->quote_snapshot['pricing']['cutFraction']]);
    }

    public function test_box_that_does_not_fit_any_sheet_is_rejected(): void
    {
        $price = PaperGrammagePrice::factory()->create();
        $quote = $this->quote($price);
        $quote['flatWidthMm'] = 1200; // longer than a 70×100 sheet in either orientation

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $quote)
            ->assertSessionHasErrors('quantity');
    }

    public function test_die_must_be_chosen_when_pricing_with_an_existing_die(): void
    {
        $price = PaperGrammagePrice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('jobs.box.store'), $this->quote($price, ['isUsingExistingDie' => true, 'dieId' => null]))
            ->assertSessionHasErrors('dieId');
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
