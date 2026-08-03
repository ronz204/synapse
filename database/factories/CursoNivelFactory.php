<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\CursoNivel;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoNivel>
 */
class CursoNivelFactory extends Factory
{
    protected $model = CursoNivel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nivel_id' => Nivel::factory(),
            'curso_id' => Curso::factory(),
            'creditos' => fake()->numberBetween(1, 6),
        ];
    }
}
