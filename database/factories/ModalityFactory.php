<?php

namespace Database\Factories;

use App\Models\Modality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modality>
 */
class ModalityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'requires_resolution' => false,
        ];
    }

    public function requiresResolution(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_resolution' => true,
        ]);
    }
}
