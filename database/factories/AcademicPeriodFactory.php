<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPeriod>
 */
class AcademicPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'year' => (int) $start->format('Y'),
            'quarter' => fake()->numberBetween(1, 3),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+4 months'),
        ];
    }
}
