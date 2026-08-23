<?php

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\Modality;
use App\Models\ModalityResolution;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\StudentPlan;
use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Src\Dashboard\Application\UseCases\GetDashboardSummaryUseCase;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard summarizes curriculum data and active students by plan and level', function () {
    $program = Program::factory()->create(['name' => 'Software Engineering']);
    $activePlan = StudyPlan::factory()->for($program)->create(['name' => 'Plan 2026']);
    StudyPlan::factory()->for($program)->create(['name' => 'Plan 2025']);
    StudyPlan::factory()->for($program)->terminal()->create(['name' => 'Plan 2024']);

    $activeEquivalency = Equivalency::factory()->create();
    Equivalency::factory()->create([
        'status' => EquivalencyStatus::Superseded,
        'superseded_by_id' => $activeEquivalency->id,
    ]);

    $student = Student::factory()->create();
    $secondStudent = Student::factory()->create();
    $inactiveStudent = Student::factory()->create(['active' => false]);

    foreach ([$student, $secondStudent] as $activeStudent) {
        StudentPlan::factory()->create([
            'student_id' => $activeStudent->id,
            'study_plan_id' => $activePlan->id,
            'current_level' => 2,
        ]);
    }

    StudentPlan::factory()->create([
        'student_id' => $inactiveStudent->id,
        'study_plan_id' => $activePlan->id,
        'current_level' => 2,
    ]);

    StudentAcademicRecord::factory()->accreditedByEquivalency()->create([
        'student_id' => $student->id,
        'academic_period_id' => null,
    ]);
    StudentAcademicRecord::factory()->accreditedByEquivalency()->create([
        'student_id' => $student->id,
        'academic_period_id' => null,
    ]);
    StudentAcademicRecord::factory()->create([
        'student_id' => $secondStudent->id,
        'academic_period_id' => null,
        'status' => AcademicRecordStatus::Passed,
    ]);

    $summary = app(GetDashboardSummaryUseCase::class)->handle(new DateTimeImmutable('2026-08-23'));

    expect($summary->studyPlans)->toBe(3)
        ->and($summary->activeStudyPlans)->toBe(2)
        ->and($summary->terminalStudyPlans)->toBe(1)
        ->and($summary->equivalencies)->toBe(4)
        ->and($summary->activeEquivalencies)->toBe(3)
        ->and($summary->supersededEquivalencies)->toBe(1)
        ->and($summary->studentsWithAccreditations)->toBe(1)
        ->and($summary->accreditedCourses)->toBe(2)
        ->and($summary->activeStudentsByLevel)->toHaveCount(1)
        ->and($summary->activeStudentsByLevel[0]->studyPlan)->toBe('Plan 2026')
        ->and($summary->activeStudentsByLevel[0]->program)->toBe('Software Engineering')
        ->and($summary->activeStudentsByLevel[0]->level)->toBe(2)
        ->and($summary->activeStudentsByLevel[0]->activeStudents)->toBe(2);
});

test('dashboard alerts use the thirty day window and current resolution validity', function () {
    $today = new DateTimeImmutable('2026-08-23');
    $program = Program::factory()->create();

    StudyPlan::factory()->for($program)->terminal()->create([
        'enrollment_closing_date' => '2026-09-10',
    ]);
    StudyPlan::factory()->for($program)->terminal()->create([
        'enrollment_closing_date' => '2026-10-10',
    ]);

    $modality = Modality::factory()->requiresResolution()->create();
    $expiringCourse = Course::factory()->for($program)->create(['modality_id' => $modality->id]);
    $unresolvedCourse = Course::factory()->for($program)->create(['modality_id' => $modality->id]);
    $futureResolutionCourse = Course::factory()->for($program)->create(['modality_id' => $modality->id]);

    ModalityResolution::factory()->create([
        'course_id' => $expiringCourse->id,
        'modality_id' => $modality->id,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-09-01',
    ]);
    ModalityResolution::factory()->create([
        'course_id' => $futureResolutionCourse->id,
        'modality_id' => $modality->id,
        'valid_from' => '2026-09-01',
        'valid_to' => null,
    ]);

    $summary = app(GetDashboardSummaryUseCase::class)->handle($today);

    expect($summary->alerts->expiringResolutions)->toBe(1)
        ->and($summary->alerts->closingTerminalPlans)->toBe(1)
        ->and($summary->alerts->coursesWithoutValidResolution)->toBe(2);

    expect(Course::query()->find($unresolvedCourse->id))->not->toBeNull();
});

test('dashboard displays real summary data and recent activity', function () {
    Carbon::setTestNow('2026-08-23 12:00:00');

    $plan = StudyPlan::factory()->create(['name' => 'Plan Dashboard 2026']);
    Equivalency::factory()->create(['resolution_number' => 'RES-DASH-001']);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Plan Dashboard 2026')
        ->assertSee('RES-DASH-001')
        ->assertSee(__('Active students by plan and level'))
        ->assertSee(__('Attention required'));

    Carbon::setTestNow();
});
