<?php

namespace Database\Factories;

use App\Models\Archivo;
use App\Models\Equiparacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Archivo>
 */
class ArchivoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'archivable_type' => Equiparacion::class,
            'archivable_id' => Equiparacion::factory(),
            'tipo_documento' => 'Resolución',
            'nombre_original' => fake()->word().'.pdf',
            'disco' => 'local',
            'ruta' => 'resoluciones/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => fake()->numberBetween(1024, 5_000_000),
            'hash_sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
