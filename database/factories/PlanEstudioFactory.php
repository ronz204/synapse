<?php

namespace Database\Factories;

use App\Enums\PlanClasificacion;
use App\Models\Carrera;
use App\Models\PlanEstudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanEstudio>
 */
class PlanEstudioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrera_id' => Carrera::factory(),
            'nombre' => 'Plan '.fake()->unique()->year(),
            'anio_implementacion' => fake()->year(),
            'clasificacion' => PlanClasificacion::Vigente,
            'fecha_cierre_matricula' => null,
        ];
    }

    public function terminal(): static
    {
        return $this->state(fn (array $attributes) => [
            'clasificacion' => PlanClasificacion::Terminal,
            'fecha_cierre_matricula' => fake()->dateTimeBetween('now', '+2 years'),
        ]);
    }
}
