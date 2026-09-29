<?php

namespace Database\Factories;

use App\Models\Press;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Press>
 */
class PressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'مطبعة '.fake()->unique()->lexify('?'),
            'is_internal' => fake()->boolean(),
            'max_colors' => fake()->randomElement([2, 4]),
            'supported_cut_fractions' => ['1/2', '1/4'],
            'supported_paper_categories' => [],
            'current_backlog_days' => fake()->numberBetween(0, 7),
            'contact_note' => null,
        ];
    }
}
