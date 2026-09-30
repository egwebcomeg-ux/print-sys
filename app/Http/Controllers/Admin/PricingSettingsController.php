<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pricing constants used by the box calculator. All seeded values are
 * PLACEHOLDERS — this page is where the real factory rates get entered.
 */
class PricingSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/pricing-settings', [
            'settings' => Setting::query()->where('group', 'pricing')->orderBy('sort_order')->get()
                ->map(fn (Setting $s) => [
                    'key' => $s->key,
                    'label' => $s->label_ar,
                    'note' => $s->note,
                    'value' => $s->value,
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spoilage_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'new_die_cost_egp' => ['required', 'numeric', 'min:0'],
            'plate_cost_per_color_egp' => ['required', 'numeric', 'min:0'],
            'press_run_rate_per_color_per_1000_sheets_egp' => ['required', 'numeric', 'min:0'],
            'lamination_rate_per_sheet_egp.matte' => ['required', 'numeric', 'min:0'],
            'lamination_rate_per_sheet_egp.gloss' => ['required', 'numeric', 'min:0'],
            'die_cut_rate_per_sheet_egp' => ['required', 'numeric', 'min:0'],
            'glue_fold_rate_per_unit_egp' => ['required', 'numeric', 'min:0'],
            'default_margin_percent' => ['required', 'numeric', 'min:0', 'max:500'],
            'billing_tolerance_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            $value = is_array($value) ? array_map('floatval', $value) : (float) $value;
            Setting::query()->whereKey($key)->update(['value' => json_encode($value)]);
        }

        Settings::forget();
        $this->toast('تم حفظ ثوابت التسعير');

        return back();
    }
}
