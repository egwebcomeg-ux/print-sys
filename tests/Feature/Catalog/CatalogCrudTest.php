<?php

namespace Tests\Feature\Catalog;

use App\Enums\PaperCategory;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\PaperSupplier;
use App\Models\PaperType;
use App\Models\Press;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_paper_type_with_grammage_and_several_supplier_prices(): void
    {
        $admin = User::factory()->admin()->create();
        [$cheap, $pricey] = PaperSupplier::factory()->count(2)->create();

        $this->actingAs($admin)->post(route('paper-types.store'), [
            'name' => 'دوبلكس ظهر رمادي',
            'category' => PaperCategory::DuplexGreyBack->value,
            'sheet_width_cm' => 70,
            'sheet_height_cm' => 100,
        ])->assertRedirect();

        $type = PaperType::query()->sole();

        $this->actingAs($admin)
            ->post(route('paper-types.grammages.store', $type), ['gsm' => 300])
            ->assertRedirect();
        $grammage = $type->grammages()->sole();

        $this->actingAs($admin)->post(route('grammages.prices.store', $grammage), [
            'paper_supplier_id' => $pricey->id, 'price_per_ton_egp' => 15000,
        ]);
        $this->actingAs($admin)->post(route('grammages.prices.store', $grammage), [
            'paper_supplier_id' => $cheap->id, 'price_per_ton_egp' => 14000,
        ]);

        // Multi-supplier: both prices are kept, cheapest is only the suggestion.
        $this->assertSame(2, $grammage->prices()->count());
        $this->assertSame($cheap->id, $grammage->cheapestPrice->paper_supplier_id);
    }

    public function test_saving_a_price_for_the_same_supplier_updates_it_instead_of_duplicating(): void
    {
        $user = User::factory()->create(); // sales may maintain prices
        $price = PaperGrammagePrice::factory()->create(['price_per_ton_egp' => 14000]);

        $this->actingAs($user)->post(route('grammages.prices.store', $price->paper_grammage_id), [
            'paper_supplier_id' => $price->paper_supplier_id,
            'price_per_ton_egp' => 14500,
        ])->assertRedirect();

        $this->assertSame(1, PaperGrammagePrice::query()->count());
        $this->assertEquals(14500, (float) $price->fresh()->price_per_ton_egp);
    }

    public function test_grammage_must_be_unique_per_paper_type(): void
    {
        $admin = User::factory()->admin()->create();
        $grammage = PaperGrammage::factory()->create(['gsm' => 300]);

        $this->actingAs($admin)
            ->post(route('paper-types.grammages.store', $grammage->paper_type_id), ['gsm' => 300])
            ->assertSessionHasErrors('gsm');
    }

    public function test_press_crud_and_backlog_update(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('presses.store'), [
            'name' => 'مطبعة ع',
            'is_internal' => '1',
            'max_colors' => 4,
            'supported_cut_fractions' => ['1/2', '1/4'],
            'current_backlog_days' => 2,
        ])->assertRedirect(route('presses.index'));

        $press = Press::query()->sole();
        $this->assertTrue($press->is_internal);
        $this->assertSame([], $press->supported_paper_categories);

        $production = User::factory()->production()->create();
        $this->actingAs($production)
            ->patch(route('presses.backlog', $press), ['current_backlog_days' => 5])
            ->assertRedirect();

        $this->assertSame(5, $press->fresh()->current_backlog_days);
    }

    public function test_customer_with_jobs_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $job = Job::factory()->create();

        $this->actingAs($admin)->delete(route('customers.destroy', $job->customer_id))->assertRedirect();

        $this->assertModelExists(Customer::query()->find($job->customer_id));
    }

    public function test_roles_are_enforced_on_catalogue_writes(): void
    {
        $sales = User::factory()->create();
        $production = User::factory()->production()->create();
        $press = Press::factory()->create();

        $this->actingAs($sales)->get(route('presses.index'))->assertOk();
        $this->actingAs($sales)->get(route('presses.create'))->assertForbidden();
        $this->actingAs($sales)->patch(route('presses.backlog', $press), ['current_backlog_days' => 1])->assertForbidden();
        $this->actingAs($production)->post(route('customers.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($sales)->get(route('users.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('pricing-settings.edit'))->assertForbidden();
    }

    public function test_every_catalogue_page_renders(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();
        $type = PaperType::query()->first();

        foreach ([
            route('customers.index'), route('customers.create'),
            route('paper-types.index'), route('paper-types.create'), route('paper-types.edit', $type),
            route('paper-suppliers.index'), route('paper-suppliers.create'),
            route('dies.index'), route('dies.create'),
            route('presses.index'), route('presses.create'),
            route('users.index'), route('users.create'),
            route('pricing-settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_pricing_settings_update(): void
    {
        $this->seed();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('pricing-settings.update'), [
            'spoilage_rate' => 0.05,
            'new_die_cost_egp' => 700,
            'plate_cost_per_color_egp' => 160,
            'press_run_rate_per_color_per_1000_sheets_egp' => 200,
            'lamination_rate_per_sheet_egp' => ['matte' => 0.4, 'gloss' => 0.35],
            'die_cut_rate_per_sheet_egp' => 0.2,
            'glue_fold_rate_per_unit_egp' => 0.06,
            'default_margin_percent' => 25,
            'billing_tolerance_percent' => 5,
        ])->assertRedirect();

        $constants = Settings::pricingConstants();
        $this->assertEquals(0.05, $constants['spoilageRate']);
        $this->assertEquals(0.4, $constants['laminationRatePerSheetEgp']['matte']);
        $this->assertEquals(25, Settings::defaultMarginPercent());
    }
}
