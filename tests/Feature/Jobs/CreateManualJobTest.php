<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobType;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateManualJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_job_is_recomputed_on_the_server_from_db_prices(): void
    {
        $grammage = PaperGrammage::factory()->create(['gsm' => 300]);
        $cheap = PaperGrammagePrice::factory()->create(['paper_grammage_id' => $grammage->id, 'price_per_ton_egp' => 14000]);
        PaperGrammagePrice::factory()->create(['paper_grammage_id' => $grammage->id, 'price_per_ton_egp' => 15000]);

        // 70×100 cm, 300 gsm, 1000 sheets = 210 kg → 210 × 14 = 2940 EGP (cheapest supplier by default)
        $paperCost = 2940.0;
        $base = $paperCost + 500;
        $final = round($base * 1.25, 2);

        $this->actingAs(User::factory()->create())->post(route('jobs.manual.store'), [
            'customer_id' => Customer::factory()->create()->id,
            'title' => 'كتيب 16 صفحة',
            'lineItems' => [[
                'id' => 'line-1', 'label' => 'غلاف',
                'paperTypeId' => (string) $grammage->paper_type_id,
                'grammageId' => (string) $grammage->id,
                'supplierPriceId' => '', // calculator's "no pick" → cheapest
                'sheetWidthCm' => 70, 'sheetHeightCm' => 100, 'sheetsCount' => 1000,
            ]],
            'costLines' => [
                ['id' => 'c1', 'label' => 'طباعة', 'amountEgp' => 500],
                ['id' => 'c2', 'label' => '', 'amountEgp' => 0],
            ],
            'marginPercent' => 25,
            'producedQuantity' => 2000,
            'finalPriceEgp' => $final,
        ])->assertRedirect();

        $job = Job::query()->sole();
        $this->assertSame(JobType::Manual, $job->job_type);
        $this->assertSame(2000, $job->quantity);
        $this->assertNull($job->produced_quantity);
        $this->assertEquals($base, (float) $job->base_cost_egp);
        $this->assertEquals($final, (float) $job->final_price_egp);

        $item = $job->paperItems()->sole();
        $this->assertEquals(210, (float) $item->weight_kg);
        $this->assertEquals($paperCost, (float) $item->cost_egp);
        $this->assertSame($cheap->id, $item->paper_grammage_price_id);

        $this->assertEqualsCanonicalizing(['ورق', 'طباعة'], $job->costLines()->pluck('label')->all());
    }

    public function test_stale_client_total_is_rejected(): void
    {
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);

        $this->actingAs(User::factory()->create())->post(route('jobs.manual.store'), [
            'customer_id' => Customer::factory()->create()->id,
            'title' => 'فلاير',
            'lineItems' => [[
                'label' => 'بند 1',
                'paperTypeId' => (string) $price->grammage->paper_type_id,
                'grammageId' => (string) $price->paper_grammage_id,
                'supplierPriceId' => (string) $price->id,
                'sheetWidthCm' => 70, 'sheetHeightCm' => 100, 'sheetsCount' => 100,
            ]],
            'costLines' => [],
            'marginPercent' => 0,
            'finalPriceEgp' => 1, // browser priced with an old price
        ])->assertSessionHasErrors('finalPriceEgp');

        $this->assertSame(0, Job::query()->count());
    }
}
