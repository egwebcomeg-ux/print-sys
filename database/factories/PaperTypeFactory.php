<?php

namespace Database\Factories;

use App\Enums\PaperCategory;
use App\Models\PaperType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperType>
 */
class PaperTypeFactory extends Factory
{
    public function definition(): array
    {
        $category = fake()->randomElement(PaperCategory::cases());

        return [
            'name' => $category->label(),
            'category' => $category,
            'sheet_width_cm' => 70,
            'sheet_height_cm' => 100,
        ];
    }
}
