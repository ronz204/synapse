<?php

namespace Database\Factories;

use App\Enums\AcademicRecordStatus;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAcademicRecord>
 */
class StudentAcademicRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'academic_period_id' => AcademicPeriod::factory(),
            'status' => AcademicRecordStatus::Passed,
            'grade' => fake()->randomFloat(2, 70, 100),
        ];
    }

    public function accreditedByEquivalency(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AcademicRecordStatus::AccreditedByEquivalency,
            'grade' => null,
            'equivalency_id' => Equivalency::factory(),
        ]);
    }
}
