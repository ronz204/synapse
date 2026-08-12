<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPlan>
 */
class StudentPlanFactory extends Factory
{
    protected $model = StudentPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'study_plan_id' => StudyPlan::factory(),
            'current_level' => fake()->numberBetween(1, 10),
        ];
    }
}
