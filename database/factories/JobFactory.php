<?php

namespace Database\Factories;

use App\Enums\BoxShape;
use App\Enums\BoxType;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\Lamination;
use App\Models\Customer;
use App\Models\Job;
use App\Models\PaperGrammage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'job_type' => JobType::Box,
            'box_type' => BoxType::Medicine,
            'box_shape' => BoxShape::ReverseTuckEnd,
            'length_cm' => 9,
            'width_cm' => 5,
            'depth_cm' => 3,
            'quantity' => 5000,
            'paper_grammage_id' => PaperGrammage::factory(),
            'print_colors' => 2,
            'lamination' => Lamination::Matte,
            'is_using_existing_die' => false,
            'base_cost_egp' => 10000,
            'margin_percent' => 20,
            'final_price_egp' => 12000,
            'status' => JobStatus::Draft,
        ];
    }

    public function box(): static
    {
        return $this->state(fn () => ['job_type' => JobType::Box]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'job_type' => JobType::Manual,
            'title' => 'كتيب '.fake()->word(),
            'box_type' => null,
            'box_shape' => null,
            'length_cm' => null,
            'width_cm' => null,
            'depth_cm' => null,
            'paper_grammage_id' => null,
            'print_colors' => 0,
            'lamination' => Lamination::None,
        ]);
    }

    public function status(JobStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
