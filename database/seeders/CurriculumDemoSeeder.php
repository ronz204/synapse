<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LaboratoryType;
use App\Enums\PlanClassification;
use App\Models\Course;
use App\Models\Level;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudyPlan;
use Illuminate\Database\Seeder;

/**
 * Fake demo data for RC-01 (Study Plan Repository), so the UI can be
 * exercised manually before any real institutional data exists.
 *
 * Called from DatabaseSeeder::run() after the catalog seeders. Can also be
 * run standalone:
 *
 *   php artisan db:seed --class=CurriculumDemoSeeder
 *
 * Safe to run more than once: courses are looked up by code, and a
 * previous run's plans/levels/students/etc. are simply left in place
 * (this seeder always creates new plan rows rather than reusing old ones,
 * so running it twice doubles the demo plans — drop and re-migrate if a
 * clean slate is needed).
 */
class CurriculumDemoSeeder extends Seeder
{
    public function run(): void
    {
        $programs = Program::query()->active()->orderBy('id')->take(2)->get();

        if ($programs->count() < 2) {
            $this->command->warn('CurriculumDemoSeeder needs at least 2 active programs — run ProgramSeeder first.');

            return;
        }

        [$primaryProgram, $secondaryProgram] = $programs;

        $courses = $this->seedCourses($primaryProgram, $secondaryProgram);

        $activePlan = $this->seedPlan(
            program: $primaryProgram,
            name: 'Demo Plan '.now()->year,
            classification: PlanClassification::Active,
            closingDate: null,
            courses: $courses,
        );

        $this->seedPlan(
            program: $primaryProgram,
            name: 'Demo Plan '.(now()->year - 6).' (Terminal)',
            classification: PlanClassification::Terminal,
            closingDate: now()->addYear(),
            courses: $courses,
        );

        $this->seedStudents($activePlan);

        $this->command->info('Curriculum demo data seeded: 2 study plans, '.count($courses).' courses, students enrolled across levels.');
    }

    /**
     * @return array<int, Course>
     */
    private function seedCourses(Program $primaryProgram, Program $secondaryProgram): array
    {
        $definitions = [
            ['code' => 'DEMO-101', 'name' => 'Introduction to Programming', 'program' => $primaryProgram],
            ['code' => 'DEMO-102', 'name' => 'Discrete Mathematics', 'program' => $primaryProgram],
            ['code' => 'DEMO-103', 'name' => 'Data Structures', 'program' => $primaryProgram],
            ['code' => 'DEMO-104', 'name' => 'Databases I', 'program' => $primaryProgram],
            ['code' => 'DEMO-105', 'name' => 'Object-Oriented Programming', 'program' => $primaryProgram],
            ['code' => 'DEMO-106', 'name' => 'Computer Networks', 'program' => $primaryProgram],
            ['code' => 'DEMO-107', 'name' => 'Operating Systems', 'program' => $primaryProgram, 'lab' => LaboratoryType::ComputerLab],
            ['code' => 'DEMO-108', 'name' => 'Software Engineering I', 'program' => $primaryProgram, 'bottleneck' => true],
            ['code' => 'DEMO-109', 'name' => 'Web Development', 'program' => $primaryProgram, 'lab' => LaboratoryType::ComputerLab],
            ['code' => 'DEMO-110', 'name' => 'Algorithms Analysis', 'program' => $primaryProgram],
            ['code' => 'DEMO-111', 'name' => 'Applied Statistics', 'program' => $secondaryProgram],
            ['code' => 'DEMO-112', 'name' => 'Cost Accounting', 'program' => $secondaryProgram],
            ['code' => 'DEMO-201', 'name' => 'English I', 'program' => null, 'service' => true],
            ['code' => 'DEMO-202', 'name' => 'English II', 'program' => null, 'service' => true],
            ['code' => 'DEMO-203', 'name' => 'Ethics and Citizenship', 'program' => null, 'service' => true],
        ];

        return array_map(function (array $definition): Course {
            $isService = $definition['service'] ?? false;

            return Course::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'program_id' => $definition['program']?->id,
                    'name' => $definition['name'],
                    'is_service' => $isService,
                    'is_bottleneck' => $definition['bottleneck'] ?? false,
                    'requires_laboratory' => isset($definition['lab']),
                    'laboratory_type' => $definition['lab'] ?? null,
                    'active' => true,
                ],
            );
        }, $definitions);
    }

    /**
     * @param  array<int, Course>  $courses
     */
    private function seedPlan(
        Program $program,
        string $name,
        PlanClassification $classification,
        ?\DateTimeInterface $closingDate,
        array $courses,
    ): StudyPlan {
        $plan = StudyPlan::query()->create([
            'program_id' => $program->id,
            'name' => $name,
            'implementation_year' => now()->year,
            'classification' => $classification,
            'enrollment_closing_date' => $closingDate,
        ]);

        // 5 levels, ~4 courses each, credits 3-5. Service courses (English,
        // Ethics) spread across the first levels like a real curriculum would.
        $levelCourseCodes = [
            1 => ['DEMO-101', 'DEMO-102', 'DEMO-201', 'DEMO-203'],
            2 => ['DEMO-103', 'DEMO-105', 'DEMO-202'],
            3 => ['DEMO-104', 'DEMO-106', 'DEMO-111'],
            4 => ['DEMO-107', 'DEMO-108', 'DEMO-112'],
            5 => ['DEMO-109', 'DEMO-110'],
        ];

        $courseByCode = collect($courses)->keyBy('code');
        $levelsByNumber = [];

        foreach ($levelCourseCodes as $number => $codes) {
            $level = Level::query()->create(['study_plan_id' => $plan->id, 'number' => $number]);
            $levelsByNumber[$number] = $level;

            foreach ($codes as $code) {
                $course = $courseByCode->get($code);

                if ($course) {
                    $level->courses()->attach($course->id, ['credits' => fake()->numberBetween(3, 5)]);
                }
            }
        }

        // A handful of valid prerequisites: required course from an earlier
        // level, dependent course from a later one, both already linked above.
        $prerequisitePairs = [
            ['DEMO-101', 'DEMO-103'],
            ['DEMO-102', 'DEMO-110'],
            ['DEMO-103', 'DEMO-104'],
            ['DEMO-105', 'DEMO-107'],
        ];

        foreach ($prerequisitePairs as [$requiredCode, $dependentCode]) {
            $required = $courseByCode->get($requiredCode);
            $dependent = $courseByCode->get($dependentCode);

            if ($required && $dependent) {
                $plan->prerequisites()->create([
                    'required_course_id' => $required->id,
                    'dependent_course_id' => $dependent->id,
                ]);
            }
        }

        return $plan;
    }

    private function seedStudents(StudyPlan $activePlan): void
    {
        // 8 active + 2 inactive, spread across levels 1-3 of the active plan,
        // so GetActiveStudentCountsUseCase has real, uneven numbers to show.
        $levelDistribution = [1, 1, 1, 2, 2, 2, 3, 3];

        foreach ($levelDistribution as $index => $level) {
            $student = Student::factory()->create(['active' => true]);

            StudentPlan::query()->create([
                'student_id' => $student->id,
                'study_plan_id' => $activePlan->id,
                'current_level' => $level,
            ]);
        }

        foreach ([1, 2] as $level) {
            $student = Student::factory()->create(['active' => false]);

            StudentPlan::query()->create([
                'student_id' => $student->id,
                'study_plan_id' => $activePlan->id,
                'current_level' => $level,
            ]);
        }
    }
}
