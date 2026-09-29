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
 * The dieline geometry (flat size, nesting pitch, pieces per box) stays in
 * the component (BOX_SHAPE_CALCULATORS) and is passed in — everything
 * money-related, including the sheet/cut plan, is recomputed here.
 * Keep both in sync.
 */
class BoxPricer
{
    // Imposition allowances (mm) — mirror the TSX constants.
    private const GRIPPER_ALLOWANCE_MM = 12;

    private const SIDE_TRIM_MM = 5;

    /** Standard raw sheets the plan may choose from (RAW_SHEET_OPTIONS in the TSX). */
    private const STANDARD_SHEETS = [[70, 100], [88, 119]];

    /**
     * @param  array{
     *   flatWidthMm: float, flatHeightMm: float, interlockPitchMm: float|null, piecesPerBox: int,
     *   quantity: int, printColors: int, lamination: string, isUsingExistingDie: bool,
     *   interlockEnabled: bool, marginPercent: float
     * }  $input
     * @return array<string, mixed>
     */
    public function price(array $input, PaperGrammage $grammage, PaperGrammagePrice $price, ?CuttingDie $die): array
    {
        $pricing = Settings::pricingConstants();
        $usingDie = $input['isUsingExistingDie'] && $die !== null;
        $quantity = max(1, (int) $input['quantity']);
        $pieces = max(1, (int) $input['piecesPerBox']);
        $flatW = (float) $input['flatWidthMm'];
        $flatH = (float) $input['flatHeightMm'];
        $pitch = $input['interlockPitchMm'] !== null ? (float) $input['interlockPitchMm'] : null;
        $nestAllowed = $input['interlockEnabled'] && $pitch !== null && ! $usingDie;
        $spoilage = 1 + $pricing['spoilageRate'];
        $pricePerTon = (float) $price->price_per_ton_egp;

        $type = $grammage->paperType;
        $paperSheet = [$type->sheet_width_cm, $type->sheet_height_cm];

        $evaluate = function (array $sheet, CutFraction $fraction) use ($flatW, $flatH, $pitch, $nestAllowed, $pieces): array {
            $cut = self::cutSheetDimsMm($sheet[0], $sheet[1], $fraction);
            $usableW = $cut['w'] - 2 * self::SIDE_TRIM_MM;
            $usableH = $cut['h'] - self::GRIPPER_ALLOWANCE_MM;
            $grid = self::upsGrid($usableW, $usableH, $flatW, $flatH);
            $nestUps = 0;
            if ($nestAllowed) {
                $cols = max(0, (int) floor($usableW / $flatW));
                $rows = $usableH >= $flatH ? (int) floor(($usableH - $flatH) / $pitch) + 1 : 0;
                $nestUps = $cols * $rows;
            }
            $nested = $nestUps > $grid;
            $raw = $nested ? $nestUps : $grid;

            return [
                'sheet' => $sheet,
                'fraction' => $fraction,
                'nested' => $nested,
                // Multi-piece boxes need whole sets per sheet.
                'ups' => intdiv($raw, $pieces) * $pieces,
            ];
        };

        if ($usingDie) {
            $plan = $evaluate($paperSheet, $die->cut_fraction);
            $upsPerCutSheet = $die->ups_on_cut_sheet;
            $plan['nested'] = false;
        } else {
            $sheets = [$paperSheet];
            foreach (self::STANDARD_SHEETS as $std) {
                if ($std !== $paperSheet) {
                    $sheets[] = $std;
                }
            }
            $candidates = [];
            foreach ($sheets as $sheet) {
                foreach ([CutFraction::Full, CutFraction::Half, CutFraction::Quarter] as $fraction) {
                    $c = $evaluate($sheet, $fraction);
                    if ($c['ups'] === 0) {
                        continue;
                    }
                    $perRaw = $c['ups'] * self::denominator($fraction);
                    $sheetsNeeded = (int) ceil((($quantity * $pieces) / $perRaw) * $spoilage);
                    $c['cost'] = $sheetsNeeded * self::sheetCost($sheet, $grammage->gsm, $pricePerTon);
                    $c['sheetsNeeded'] = $sheetsNeeded;
                    $candidates[] = $c;
                }
            }
            if ($candidates === []) {
                return ['fits' => false];
            }
            // Cheapest paper for the run; ties → fewer sheets → fewer, larger cuts.
            usort($candidates, fn ($a, $b) => [$a['cost'], $a['sheetsNeeded'], self::denominator($a['fraction'])]
                <=> [$b['cost'], $b['sheetsNeeded'], self::denominator($b['fraction'])]);
            $plan = $candidates[0];
            $upsPerCutSheet = $plan['ups'];
        }

        $upsPerRawSheet = $upsPerCutSheet * self::denominator($plan['fraction']);
        if ($upsPerRawSheet < 1) {
            return ['fits' => false];
        }

        $rawSheetsNeeded = (int) ceil((($quantity * $pieces) / $upsPerRawSheet) * $spoilage);
        $paperCostPerSheet = self::sheetCost($plan['sheet'], $grammage->gsm, $pricePerTon);

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
            'sheetWidthCm' => $plan['sheet'][0],
            'sheetHeightCm' => $plan['sheet'][1],
            'cutFraction' => $plan['fraction']->value,
            'piecesPerBox' => $pieces,
            'rawSheetsNeeded' => $rawSheetsNeeded,
            'upsPerRawSheet' => $upsPerRawSheet,
            'upsPerCutSheet' => $upsPerCutSheet,
            'interlocked' => $plan['nested'],
            'costBreakdown' => array_map(fn ($v) => round($v, 2), $breakdown),
            'baseCostEgp' => round($baseCost, 2),
            'marginPercent' => $margin,
            'marginAmountEgp' => round($marginAmount, 2),
            'unitPriceEgp' => $unitPrice,
            'totalPriceEgp' => $totalPrice,
        ];
    }

    /** @param array{0: int, 1: int} $sheet cm */
    private static function sheetCost(array $sheet, int $gsm, float $pricePerTon): float
    {
        $areaM2 = ($sheet[0] / 100) * ($sheet[1] / 100);

        return ($areaM2 * $gsm / 1000) * ($pricePerTon / 1000);
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
