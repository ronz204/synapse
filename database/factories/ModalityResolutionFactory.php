<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Modality;
use App\Models\ModalityResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModalityResolution>
 */
class ModalityResolutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'modality_id' => Modality::factory()->requiresResolution(),
            'resolution_number' => 'R-'.fake()->unique()->numberBetween(1000, 9999),
            'approving_body' => 'Consejo Universitario',
            'valid_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'valid_to' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => fake()->dateTimeBetween('-3 years', '-2 years'),
            'valid_to' => fake()->dateTimeBetween('-2 years', '-1 year'),
        ]);
    }
}
