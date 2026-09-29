<?php

namespace Database\Factories;

use App\Models\PaperSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperSupplier>
 */
class PaperSupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact_note' => '01'.fake()->numerify('#########'),
            'performance_notes' => null,
        ];
    }
}
