<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Prerequisite;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prerequisite>
 */
class PrerequisiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_plan_id' => StudyPlan::factory(),
            'required_course_id' => Course::factory(),
            'dependent_course_id' => Course::factory(),
        ];
    }
}
