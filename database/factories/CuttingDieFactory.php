<?php

namespace Database\Factories;

use App\Enums\ClosureType;
use App\Enums\CutFraction;
use App\Enums\DieCondition;
use App\Models\CuttingDie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CuttingDie>
 */
class CuttingDieFactory extends Factory
{
    protected $model = CuttingDie::class;

    public function definition(): array
    {
        return [
            'code' => 'D-'.fake()->unique()->numerify('######'),
            'name' => 'اسطامبة '.fake()->numerify('###'),
            'length_cm' => fake()->numberBetween(8, 40),
            'width_cm' => fake()->numberBetween(5, 20),
            'depth_cm' => fake()->numberBetween(3, 15),
            'closure_type' => fake()->randomElement(ClosureType::cases()),
            'rack_location' => 'ستاند أ - رف '.fake()->numberBetween(1, 6),
            'ups_on_cut_sheet' => fake()->numberBetween(2, 10),
            'cut_fraction' => fake()->randomElement(CutFraction::cases()),
            'condition' => DieCondition::Ready,
        ];
    }
}
