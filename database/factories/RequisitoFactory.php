<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\Requisito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisito>
 */
class RequisitoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_estudio_id' => PlanEstudio::factory(),
            'curso_requerido_id' => Curso::factory(),
            'curso_exige_id' => Curso::factory(),
        ];
    }
}
