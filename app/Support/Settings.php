<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to the `settings` table (pricing constants etc.).
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Pricing constants in the shape QuickBoxPricingCalculator's
     * `pricingConstants` prop expects. All values are PLACEHOLDERS until the
     * factory confirms real rates on the pricing settings page.
     *
     * @return array<string, mixed>
     */
    public static function pricingConstants(): array
    {
        $lamination = self::get('lamination_rate_per_sheet_egp', []);

        return [
            'spoilageRate' => (float) self::get('spoilage_rate', 0.03),
            'newDieCostEgp' => (float) self::get('new_die_cost_egp', 650),
            'plateCostPerColorEgp' => (float) self::get('plate_cost_per_color_egp', 150),
            'pressRunRatePerColorPer1000SheetsEgp' => (float) self::get('press_run_rate_per_color_per_1000_sheets_egp', 180),
            'laminationRatePerSheetEgp' => [
                'matte' => (float) ($lamination['matte'] ?? 0.35),
                'gloss' => (float) ($lamination['gloss'] ?? 0.30),
            ],
            'dieCutRatePerSheetEgp' => (float) self::get('die_cut_rate_per_sheet_egp', 0.15),
            'glueFoldRatePerUnitEgp' => (float) self::get('glue_fold_rate_per_unit_egp', 0.05),
        ];
    }

    public static function defaultMarginPercent(): float
    {
        return (float) self::get('default_margin_percent', 20);
    }
}
