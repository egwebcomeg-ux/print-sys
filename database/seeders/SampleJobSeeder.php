<?php

namespace Database\Seeders;

use App\Enums\BoxShape;
use App\Enums\BoxType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\Lamination;
use App\Models\Customer;
use App\Models\CuttingDie;
use App\Models\Job;
use App\Models\PaperGrammage;
use App\Models\PaperSupplier;
use Illuminate\Database\Seeder;

/**
 * Two sample customers and the two sample past jobs that fed the
 * "كرر نفس الشغلانة" shortcut. Prices are illustrative only.
 */
class SampleJobSeeder extends Seeder
{
    public function run(): void
    {
        if (Job::query()->exists()) {
            return;
        }

        $ezaby = Customer::query()->firstOrCreate(['name' => 'صيدلية العزبي'], ['phone' => '01000000001']);
        $hag = Customer::query()->firstOrCreate(['name' => 'حلواني الحاج'], ['phone' => '01000000002']);

        $this->job($ezaby, BoxType::Medicine, BoxShape::ReverseTuckEnd, [9, 5, 3], 5000, 'دوبلكس ظهر رمادي', 300, 'مصنع الأهرام للورق', 2, Lamination::Matte, 'D-301810', 4200);
        $this->job($hag, BoxType::Candy, BoxShape::AutoLockBottom, [20, 15, 8], 3000, 'دوبلكس ظهر أبيض', 300, 'شركة النصر للورق', 4, Lamination::Gloss, null, 9800);
    }

    /** @param array{0: float, 1: float, 2: float} $dims */
    private function job(
        Customer $customer, BoxType $type, BoxShape $shape, array $dims, int $quantity,
        string $paperName, int $gsm, string $supplierName, int $colors, Lamination $lamination,
        ?string $dieCode, float $baseCost,
    ): void {
        $grammage = PaperGrammage::query()
            ->whereRelation('paperType', 'name', $paperName)
            ->where('gsm', $gsm)
            ->firstOrFail();
        $supplier = PaperSupplier::query()->where('name', $supplierName)->firstOrFail();
        $price = $grammage->prices()->where('paper_supplier_id', $supplier->id)->first();
        $die = $dieCode ? CuttingDie::query()->where('code', $dieCode)->first() : null;
        $margin = 20;

        Job::query()->create([
            'customer_id' => $customer->id,
            'job_type' => JobType::Box,
            'box_type' => $type,
            'box_shape' => $shape,
            'length_cm' => $dims[0],
            'width_cm' => $dims[1],
            'depth_cm' => $dims[2],
            'quantity' => $quantity,
            'paper_grammage_id' => $grammage->id,
            'paper_grammage_price_id' => $price?->id,
            'print_colors' => $colors,
            'lamination' => $lamination,
            'is_using_existing_die' => $die !== null,
            'die_id' => $die?->id,
            'base_cost_egp' => $baseCost,
            'margin_percent' => $margin,
            'final_price_egp' => round($baseCost * (1 + $margin / 100), 2),
            'status' => JobStatus::Quoted,
        ]);
    }
}
