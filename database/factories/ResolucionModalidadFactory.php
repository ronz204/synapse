<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Modalidad;
use App\Models\ResolucionModalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResolucionModalidad>
 */
class ResolucionModalidadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'modalidad_id' => Modalidad::factory()->requiereResolucion(),
            'numero_resolucion' => 'R-'.fake()->unique()->numberBetween(1000, 9999),
            'organo_aprobador' => 'Consejo Universitario',
            'vigencia_inicio' => fake()->dateTimeBetween('-1 year', 'now'),
            'vigencia_fin' => null,
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'vigencia_inicio' => fake()->dateTimeBetween('-3 years', '-2 years'),
            'vigencia_fin' => fake()->dateTimeBetween('-2 years', '-1 year'),
        ]);
    }
}
