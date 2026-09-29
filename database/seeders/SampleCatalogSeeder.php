<?php

namespace Database\Seeders;

use App\Enums\ClosureType;
use App\Enums\CutFraction;
use App\Enums\DieCondition;
use App\Enums\PaperCategory;
use App\Models\CuttingDie;
use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\PaperSupplier;
use App\Models\PaperType;
use App\Models\Press;
use Illuminate\Database\Seeder;

/**
 * Sample catalogue mirroring the sample data that used to be hardcoded in
 * the React calculators, so every page works on first boot. ALL PRICES AND
 * PRESS/DIE DATA ARE PLACEHOLDERS — replace them from the catalogue pages.
 */
class SampleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPaper();
        $this->seedDies();
        $this->seedPresses();
    }

    private function seedPaper(): void
    {
        // Same spread as the old `sp()` helper: 0.98 / 1.00 / 1.04 × base price.
        $suppliers = [
            [PaperSupplier::query()->firstOrCreate(['name' => 'مصنع الأهرام للورق']), 0.98],
            [PaperSupplier::query()->firstOrCreate(['name' => 'شركة النصر للورق']), 1.00],
            [PaperSupplier::query()->firstOrCreate(['name' => 'مستورد المعادي']), 1.04],
        ];

        $catalogue = [
            ['دوبلكس ظهر رمادي', PaperCategory::DuplexGreyBack, [250 => 14000, 300 => 14500, 350 => 15200, 400 => 16000]],
            ['دوبلكس ظهر أبيض', PaperCategory::DuplexWhiteBack, [250 => 15500, 300 => 16200, 350 => 17000]],
            ['بريستول ظهر أبيض', PaperCategory::BristolWhiteBack, [250 => 17500, 300 => 18300, 350 => 19000]],
            ['كرافت بني نقي', PaperCategory::KraftLiner, [150 => 12500, 200 => 13000, 250 => 13800]],
            ['كوشيه لامع', PaperCategory::Couche, [150 => 16000, 200 => 16800, 250 => 17600]],
        ];

        foreach ($catalogue as [$name, $category, $grammages]) {
            $type = PaperType::query()->firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'sheet_width_cm' => 70, 'sheet_height_cm' => 100],
            );

            foreach ($grammages as $gsm => $basePrice) {
                $grammage = PaperGrammage::query()->firstOrCreate(['paper_type_id' => $type->id, 'gsm' => $gsm]);

                foreach ($suppliers as [$supplier, $factor]) {
                    PaperGrammagePrice::query()->firstOrCreate(
                        ['paper_grammage_id' => $grammage->id, 'paper_supplier_id' => $supplier->id],
                        ['price_per_ton_egp' => round($basePrice * $factor), 'price_as_of' => now()],
                    );
                }
            }
        }
    }

    private function seedDies(): void
    {
        $dies = [
            ['D-301810', 'اسطامبة صيدلي 060', 30, 18, 10, ClosureType::ReverseTuck, 'ستاند أ - رف 3', 6, CutFraction::Half, DieCondition::Ready],
            ['D-251512', 'اسطامبة كوزمتك 042', 25, 15, 12, ClosureType::AutoBottom, 'ستاند ب - رف 1', 8, CutFraction::Half, DieCondition::Ready],
            ['D-402015', 'اسطامبة أغذية 018', 40, 20, 15, ClosureType::StraightTuck, 'ستاند أ - رف 5', 4, CutFraction::Full, DieCondition::NeedsRubber],
            ['D-181808', 'اسطامبة هدايا مربع', 18, 18, 8, ClosureType::SnapLock, 'ستاند ج - رف 2', 10, CutFraction::Quarter, DieCondition::Ready],
            ['D-352010', 'اسطامبة إلكترونيات 077', 35, 20, 10, ClosureType::ReverseTuck, 'ستاند ب - رف 4', 6, CutFraction::Half, DieCondition::Maintenance],
        ];

        foreach ($dies as [$code, $name, $l, $w, $d, $closure, $rack, $ups, $fraction, $condition]) {
            CuttingDie::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'length_cm' => $l, 'width_cm' => $w, 'depth_cm' => $d,
                'closure_type' => $closure, 'rack_location' => $rack, 'ups_on_cut_sheet' => $ups,
                'cut_fraction' => $fraction, 'condition' => $condition,
            ]);
        }
    }

    private function seedPresses(): void
    {
        $presses = [
            ['مطبعة ع (داخلية)', true, 2, ['1/4', '1/6', '1/8'], [], 1, 'خط الإنتاج الداخلي — أ. محمود'],
            ['مطبعة ص (مقاول خارجي)', false, 4, ['1/2'], [], 4, '01xxxxxxxxx'],
            ['مطبعة ج (مقاول خارجي)', false, 2, ['1/2'], [], 2, '01xxxxxxxxx'],
            ['مطبعة ف (داخلية)', true, 4, ['1/1', '1/2'], ['kraft_liner', 'duplex_grey_back'], 6, 'خط الإنتاج الداخلي — أ. سامي'],
        ];

        foreach ($presses as [$name, $internal, $colors, $fractions, $categories, $backlog, $contact]) {
            Press::query()->firstOrCreate(['name' => $name], [
                'is_internal' => $internal, 'max_colors' => $colors, 'supported_cut_fractions' => $fractions,
                'supported_paper_categories' => $categories, 'current_backlog_days' => $backlog, 'contact_note' => $contact,
            ]);
        }
    }
}
