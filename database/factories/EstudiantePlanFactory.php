<?php

namespace Database\Factories;

use App\Models\Estudiante;
use App\Models\EstudiantePlan;
use App\Models\PlanEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstudiantePlan>
 */
class EstudiantePlanFactory extends Factory
{
    protected $model = EstudiantePlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'plan_estudio_id' => PlanEstudio::factory(),
            'nivel_actual' => fake()->numberBetween(1, 10),
        ];
    }
}
