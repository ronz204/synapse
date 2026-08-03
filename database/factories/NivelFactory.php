<?php

namespace Database\Factories;

use App\Models\Nivel;
use App\Models\PlanEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nivel>
 */
class NivelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_estudio_id' => PlanEstudio::factory(),
            'numero' => fake()->unique()->numberBetween(1, 10),
        ];
    }
}
