<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Equivalency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'documentable_type' => Equivalency::class,
            'documentable_id' => Equivalency::factory(),
            'document_type' => 'Resolución',
            'original_name' => fake()->word().'.pdf',
            'disk' => 'local',
            'path' => 'resoluciones/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'hash_sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
