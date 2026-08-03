<?php

namespace Database\Factories;

use App\Models\PeriodoAcademico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodoAcademico>
 */
class PeriodoAcademicoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'anio' => (int) $inicio->format('Y'),
            'cuatrimestre' => fake()->numberBetween(1, 3),
            'fecha_inicio' => $inicio,
            'fecha_fin' => (clone $inicio)->modify('+4 months'),
        ];
    }
}
