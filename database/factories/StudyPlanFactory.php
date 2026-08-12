<?php

namespace Database\Factories;

use App\Enums\PlanClassification;
use App\Models\Program;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'name' => 'Plan '.fake()->unique()->year(),
            'implementation_year' => fake()->year(),
            'classification' => PlanClassification::Active,
            'enrollment_closing_date' => null,
        ];
    }

    public function terminal(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => PlanClassification::Terminal,
            'enrollment_closing_date' => fake()->dateTimeBetween('now', '+2 years'),
        ]);
    }
}
