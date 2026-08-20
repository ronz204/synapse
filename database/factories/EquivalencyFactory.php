<?php

namespace Database\Factories;

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Equivalency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equivalency>
 */
class EquivalencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Independent courses guarantee source_course_id <> target_course_id.
            'source_course_id' => Course::factory(),
            'target_course_id' => Course::factory(),
            'direction' => fake()->randomElement(EquivalencyDirection::cases()),
            'resolution_number' => 'R-'.fake()->unique()->numberBetween(1000, 9999),
            // Explicit even though the column defaults to 'active' in the DB:
            // Eloquent doesn't re-read DB defaults after the insert, so without
            // this the in-memory attribute stays null despite the row being
            // correct.
            'status' => EquivalencyStatus::Active,
        ];
    }

    public function oldToNew(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => EquivalencyDirection::OldToNew,
        ]);
    }

    public function newToOld(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => EquivalencyDirection::NewToOld,
        ]);
    }

    public function bidirectional(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => EquivalencyDirection::Bidirectional,
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EquivalencyStatus::Superseded,
            'superseded_by_id' => Equivalency::factory(),
        ]);
    }
}
