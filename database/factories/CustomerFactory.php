<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => '01'.fake()->numerify('#########'),
            'email' => fake()->optional()->safeEmail(),
            'credit_limit_egp' => null,
            'notes' => null,
        ];
    }
}
