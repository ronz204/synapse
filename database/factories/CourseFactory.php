<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Modality;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            // "Presencial"'s id isn't hardcoded: it's resolved by name so this
            // doesn't depend on ModalitySeeder having run first.
            'modality_id' => Modality::query()->firstOrCreate(
                ['name' => 'Presencial'],
                ['requires_resolution' => false],
            )->id,
            'code' => fake()->unique()->bothify('???-###'),
            'name' => fake()->sentence(3),
            'is_service' => false,
            'is_bottleneck' => false,
            'requires_laboratory' => false,
            'laboratory_type' => null,
            'active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'program_id' => null,
            'is_service' => true,
        ]);
    }
}
