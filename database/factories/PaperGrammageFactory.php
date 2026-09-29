<?php

namespace Database\Factories;

use App\Models\PaperGrammage;
use App\Models\PaperType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperGrammage>
 */
class PaperGrammageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paper_type_id' => PaperType::factory(),
            'gsm' => fake()->unique()->numberBetween(100, 450),
        ];
    }
}
