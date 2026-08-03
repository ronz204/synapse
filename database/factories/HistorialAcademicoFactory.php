<?php

namespace Database\Factories;

use App\Enums\HistorialAcademicoEstado;
use App\Models\Curso;
use App\Models\Equiparacion;
use App\Models\Estudiante;
use App\Models\HistorialAcademico;
use App\Models\PeriodoAcademico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistorialAcademico>
 */
class HistorialAcademicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'curso_id' => Curso::factory(),
            'periodo_academico_id' => PeriodoAcademico::factory(),
            'estado' => HistorialAcademicoEstado::Aprobado,
            'nota' => fake()->randomFloat(2, 70, 100),
        ];
    }

    public function acreditadoPorEquiparacion(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => HistorialAcademicoEstado::AcreditadoPorEquiparacion,
            'nota' => null,
            'equiparacion_id' => Equiparacion::factory(),
        ]);
    }
}
