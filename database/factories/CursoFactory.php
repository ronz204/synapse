<?php

namespace Database\Factories;

use App\Models\Carrera;
use App\Models\Curso;
use App\Models\Modalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrera_id' => Carrera::factory(),
            // No se hardcodea el id de "Presencial": se resuelve por nombre
            // para no depender de que ModalidadSeeder haya corrido antes.
            'modalidad_id' => Modalidad::query()->firstOrCreate(
                ['nombre' => 'Presencial'],
                ['requiere_resolucion' => false],
            )->id,
            'codigo' => fake()->unique()->bothify('???-###'),
            'nombre' => fake()->sentence(3),
            'es_servicio' => false,
            'es_cuello_botella' => false,
            'requiere_laboratorio' => false,
            'tipo_laboratorio' => null,
            'activo' => true,
        ];
    }

    public function servicio(): static
    {
        return $this->state(fn (array $attributes) => [
            'carrera_id' => null,
            'es_servicio' => true,
        ]);
    }
}
