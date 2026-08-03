<?php

namespace Database\Factories;

use App\Enums\EquiparacionEstado;
use App\Enums\EquiparacionSentido;
use App\Models\Curso;
use App\Models\Equiparacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equiparacion>
 */
class EquiparacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Cursos independientes garantizan curso_origen_id <> curso_destino_id.
            'curso_origen_id' => Curso::factory(),
            'curso_destino_id' => Curso::factory(),
            'sentido' => fake()->randomElement(EquiparacionSentido::cases()),
            'numero_resolucion' => 'R-'.fake()->unique()->numberBetween(1000, 9999),
            // Explícito aunque la columna tenga default 'Vigente' en BD: Eloquent
            // no relee defaults de BD tras el insert, así que sin esto el atributo
            // queda null en memoria pese a que la fila sí quedó correcta.
            'estado' => EquiparacionEstado::Vigente,
        ];
    }

    public function anteriorANuevo(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentido' => EquiparacionSentido::AnteriorANuevo,
        ]);
    }

    public function nuevoAAnterior(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentido' => EquiparacionSentido::NuevoAAnterior,
        ]);
    }

    public function bidireccional(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentido' => EquiparacionSentido::Bidireccional,
        ]);
    }

    public function sustituida(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EquiparacionEstado::Sustituida,
            'sustituida_por_id' => Equiparacion::factory(),
        ]);
    }
}
