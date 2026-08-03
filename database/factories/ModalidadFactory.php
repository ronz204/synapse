<?php

namespace Database\Factories;

use App\Models\Modalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modalidad>
 */
class ModalidadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'requiere_resolucion' => false,
        ];
    }

    public function requiereResolucion(): static
    {
        return $this->state(fn (array $attributes) => [
            'requiere_resolucion' => true,
        ]);
    }
}
