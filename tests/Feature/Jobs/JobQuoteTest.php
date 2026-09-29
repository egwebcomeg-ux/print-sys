<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_page_totals_include_vat_from_company_settings(): void
    {
        $this->seed();
        $job = Job::factory()->create(['quantity' => 5000, 'final_price_egp' => 12000]);

        $this->actingAs(User::factory()->create())
            ->get(route('jobs.quote', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('print/quote')
                ->where('quote.number', sprintf('Q-%05d', $job->id))
                ->where('quote.unitPrice', 2.4)
                ->where('quote.subtotal', 12000)
                ->where('quote.vatPercent', 14)
                ->where('quote.vat', 1680)
                ->where('quote.total', 13680)
                ->where('company.name', 'Pantopack'));
    }

    public function test_admin_can_update_company_settings(): void
    {
        $this->seed();

        $this->actingAs(User::factory()->admin()->create())->put(route('company-settings.update'), [
            'company_name' => 'مصنع بانتوباك',
            'company_phone' => '0100',
            'quote_vat_percent' => 0,
            'quote_validity_days' => 30,
            'quote_notes' => 'x',
        ])->assertRedirect();

        $company = Settings::company();
        $this->assertSame('مصنع بانتوباك', $company['name']);
        $this->assertSame(0.0, $company['vatPercent']);
        $this->assertSame(30, $company['validityDays']);

        $this->actingAs(User::factory()->create())->get(route('company-settings.edit'))->assertForbidden();
    }
}
