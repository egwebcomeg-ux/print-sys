<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_name' => fake()->name(),
            'company_name' => fake()->company(),
            'phone' => '01'.fake()->numerify('#########'),
            'source' => null,
            'status' => LeadStatus::New,
            'expected_quantity' => fake()->randomElement([1000, 3000, 5000]),
            'notes' => null,
        ];
    }
}
