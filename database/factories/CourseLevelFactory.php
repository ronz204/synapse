<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLevel>
 */
class CourseLevelFactory extends Factory
{
    protected $model = CourseLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level_id' => Level::factory(),
            'course_id' => Course::factory(),
            'credits' => fake()->numberBetween(1, 6),
        ];
    }
}
