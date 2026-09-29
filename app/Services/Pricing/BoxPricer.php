<?php

namespace App\Services\Pricing;

use App\Enums\CutFraction;
use App\Enums\Lamination;
use App\Models\CuttingDie;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Support\Settings;

/**
 * Server-side twin of the `calc` in QuickBoxPricingCalculator.tsx. The
 * browser prices live for the user; this recomputes the same numbers from
 * DB prices and settings so a tampered payload can't be saved or invoiced.
 *
 * The dieline geometry (flat width/height) stays in the component
 * (BOX_SHAPE_CALCULATORS, an acknowledged placeholder) and is passed in —
 * everything money-related is recomputed here. Keep both in sync.
 */
class BoxPricer
{
    // Imposition allowances (mm) — PLACEHOLDERS, mirror the TSX constants.
    private const GRIPPER_ALLOWANCE_MM = 12;

    private const SIDE_TRIM_MM = 5;

    private const INTERLOCK_HEIGHT_SAVING_RATIO = 0.85; // PLACEHOLDER

    private const INTERLOCK_CAPABLE_SHAPES = ['reverse_tuck_end', 'straight_tuck_end', 'auto_lock_bottom'];

    /**
     * @param  array{
     *   flatWidthMm: float, flatHeightMm: float, shape: string, quantity: int, printColors: int,
     *   lamination: string, isUsingExistingDie: bool, interlockEnabled: bool, marginPercent: float
     * }  $input
     * @return array<string, mixed>
     */
    public function price(array $input, PaperGrammage $grammage, PaperGrammagePrice $price, ?CuttingDie $die): array
    {
        $pricing = Settings::pricingConstants();
        $usingDie = $input['isUsingExistingDie'] && $die !== null;
        $quantity = max(1, (int) $input['quantity']);
        $flatW = (float) $input['flatWidthMm'];
        $flatH = (float) $input['flatHeightMm'];

        // Sheet: the paper type's own stock size drives cost AND imposition.
        $type = $grammage->paperType;
        // Without a die, price on a half sheet; a box too big for it falls back to the full sheet.
        $fraction = $usingDie ? $die->cut_fraction : CutFraction::Half;
        if (! $usingDie) {
            $half = self::cutSheetDimsMm($type->sheet_width_cm, $type->sheet_height_cm, CutFraction::Half);
            if (self::upsGrid($half['w'] - 2 * self::SIDE_TRIM_MM, $half['h'] - self::GRIPPER_ALLOWANCE_MM, $flatW, $flatH) === 0) {
                $fraction = CutFraction::Full;
            }
        }
        $cut = self::cutSheetDimsMm($type->sheet_width_cm, $type->sheet_height_cm, $fraction);
        $usableW = $cut['w'] - 2 * self::SIDE_TRIM_MM;
        $usableH = $cut['h'] - self::GRIPPER_ALLOWANCE_MM;

        $grid = self::upsGrid($usableW, $usableH, $flatW, $flatH);
        $interlockUps = 0;
        if (! $usingDie && $input['interlockEnabled'] && in_array($input['shape'], self::INTERLOCK_CAPABLE_SHAPES, true)) {
            $cols = max(0, (int) floor($usableW / $flatW));
            $pitch = $flatH * self::INTERLOCK_HEIGHT_SAVING_RATIO;
            $rows = $usableH >= $flatH ? (int) floor(($usableH - $flatH) / $pitch) + 1 : 0;
            $interlockUps = $cols * $rows;
        }
        // Interlock only when it actually beats the plain grid.
        $useInterlock = $interlockUps > $grid;

        $upsPerCutSheet = $usingDie ? $die->ups_on_cut_sheet : max($grid, $interlockUps);
        $cutSheetsPerRaw = self::denominator($fraction);
        $upsPerRawSheet = $upsPerCutSheet * $cutSheetsPerRaw;

        // Nothing fits → not a priceable job (the UI blocks confirm too).
        if ($upsPerRawSheet < 1) {
            return ['fits' => false];
        }

        $rawSheetsNeeded = (int) ceil(($quantity / $upsPerRawSheet) * (1 + $pricing['spoilageRate']));

        $areaM2 = ($type->sheet_width_cm / 100) * ($type->sheet_height_cm / 100);
        $paperCostPerSheet = ($areaM2 * $grammage->gsm / 1000) * ((float) $price->price_per_ton_egp / 1000);

        $colors = (int) $input['printColors'];
        $lamination = Lamination::from($input['lamination']);

        $breakdown = [
            'paperCost' => $rawSheetsNeeded * $paperCostPerSheet,
            'platesCost' => $colors > 0 ? $colors * $pricing['plateCostPerColorEgp'] : 0,
            'pressRunCost' => $colors > 0 ? $colors * $pricing['pressRunRatePerColorPer1000SheetsEgp'] * ($rawSheetsNeeded / 1000) : 0,
            'laminationCost' => $lamination === Lamination::None ? 0 : $rawSheetsNeeded * $pricing['laminationRatePerSheetEgp'][$lamination->value],
            'dieToolingCost' => $input['isUsingExistingDie'] ? 0 : $pricing['newDieCostEgp'],
            'dieCuttingRunCost' => $rawSheetsNeeded * $pricing['dieCutRatePerSheetEgp'],
            'gluingCost' => $quantity * $pricing['glueFoldRatePerUnitEgp'],
        ];

        $baseCost = array_sum($breakdown);
        $margin = max(0, (float) $input['marginPercent']);
        $marginAmount = $baseCost * ($margin / 100);
        $unitPrice = round(($baseCost + $marginAmount) / $quantity, 2);
        $totalPrice = round($unitPrice * $quantity, 2);

        return [
            'fits' => true,
            'rawSheetsNeeded' => $rawSheetsNeeded,
            'upsPerRawSheet' => $upsPerRawSheet,
            'upsPerCutSheet' => $upsPerCutSheet,
            'interlocked' => $useInterlock,
            'costBreakdown' => array_map(fn ($v) => round($v, 2), $breakdown),
            'baseCostEgp' => round($baseCost, 2),
            'marginPercent' => $margin,
            'marginAmountEgp' => round($marginAmount, 2),
            'unitPriceEgp' => $unitPrice,
            'totalPriceEgp' => $totalPrice,
        ];
    }

    /** @return array{w: float, h: float} */
    private static function cutSheetDimsMm(int $rawWidthCm, int $rawHeightCm, CutFraction $fraction): array
    {
        $w = $rawWidthCm * 10;
        $h = $rawHeightCm * 10;

        return match ($fraction) {
            CutFraction::Full => ['w' => $w, 'h' => $h],
            CutFraction::Half => ['w' => $w, 'h' => $h / 2],
            CutFraction::Quarter => ['w' => $w / 2, 'h' => $h / 2],
            CutFraction::Sixth => ['w' => $w / 3, 'h' => $h / 2],
            CutFraction::Eighth => ['w' => $w / 4, 'h' => $h / 2],
        };
    }

    private static function denominator(CutFraction $fraction): int
    {
        return match ($fraction) {
            CutFraction::Full => 1,
            CutFraction::Half => 2,
            CutFraction::Quarter => 4,
            CutFraction::Sixth => 6,
            CutFraction::Eighth => 8,
        };
    }

    /** Plain grid packing, best of both orientations (computeUpsGrid in the TSX). */
    private static function upsGrid(float $usableW, float $usableH, float $flatW, float $flatH): int
    {
        if ($flatW <= 0 || $flatH <= 0) {
            return 0;
        }

        $normal = (int) floor($usableW / $flatW) * (int) floor($usableH / $flatH);
        $rotated = (int) floor($usableW / $flatH) * (int) floor($usableH / $flatW);

        return max(0, $normal, $rotated);
    }
}
