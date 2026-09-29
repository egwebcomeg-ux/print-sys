<?php

namespace Database\Factories;

use App\Models\PaperGrammage;
use App\Models\PaperGrammagePrice;
use App\Models\PaperSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperGrammagePrice>
 */
class PaperGrammagePriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paper_grammage_id' => PaperGrammage::factory(),
            'paper_supplier_id' => PaperSupplier::factory(),
            'price_per_ton_egp' => fake()->numberBetween(12000, 20000),
            'price_as_of' => now(),
        ];
    }
}
