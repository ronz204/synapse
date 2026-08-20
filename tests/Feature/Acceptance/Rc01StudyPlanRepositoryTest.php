<?php

declare(strict_types=1);

/**
 * RC-01 — Repositorio de Planes de Estudio.
 *
 * One test per acceptance criterion listed in `.claude/docs/approach.md`, so
 * a red test here reads directly as "this graded criterion is not met" rather
 * than as an internal regression. Overlap with the per-component suites is
 * deliberate: those test the implementation, these test the requirement.
 */

use App\Enums\PlanClassification;
use App\Models\Course;
use App\Models\Level;
use App\Models\Prerequisite;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentPlan;
use App\Models\StudyPlan;
use Livewire\Livewire;
use Src\Curriculum\StudyPlan\Application\UseCases\GetActiveStudentCountsUseCase;
use Src\Curriculum\StudyPlan\Presentation\Livewire\StudyPlanComponent;

it('RC-01 AC1 — selecting a plan displays its full structure (levels, courses, prerequisites) and its classification', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create([
        'program_id' => $program->id,
        'classification' => PlanClassification::Active,
    ]);

    $level = Level::factory()->create(['study_plan_id' => $plan->id, 'number' => 1]);
    $required = Course::factory()->create(['program_id' => $program->id, 'code' => 'RC1-101']);
    $dependent = Course::factory()->create(['program_id' => $program->id, 'code' => 'RC1-102']);
    $level->courses()->attach([$required->id => ['credits' => 4], $dependent->id => ['credits' => 3]]);

    $plan->prerequisites()->create([
        'required_course_id' => $required->id,
        'dependent_course_id' => $dependent->id,
    ]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        // Level.
        ->assertSee((string) $level->number)
        // Courses within the level.
        ->assertSee('RC1-101')
        ->assertSee('RC1-102')
        // Classification, shown next to the plan name.
        ->assertSee(__(PlanClassification::Active->name))
        // Prerequisites section, listing the pair.
        ->assertSee(__('Prerequisites'));
});

it('RC-01 AC2 — a Terminal plan additionally shows its enrollment closing date', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $plan = StudyPlan::factory()->terminal()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->assertSee(__(PlanClassification::Terminal->name))
        ->assertSee($plan->enrollment_closing_date->format('Y-m-d'));
});

it('RC-01 AC2 — the enrollment closing date is a hard requirement for a Terminal plan, not a warning', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->set('form.programId', $program->id)
        ->set('form.name', 'RC-01 Terminal Plan')
        ->set('form.implementationYear', '2018')
        ->set('form.classification', PlanClassification::Terminal->value)
        ->set('form.enrollmentClosingDate', null)
        ->call('save')
        ->assertHasErrors(['form.enrollmentClosingDate']);

    expect(StudyPlan::query()->where('name', 'RC-01 Terminal Plan')->exists())->toBeFalse();
});

it('RC-01 AC3 — a prerequisite citing a course that does not exist in the plan cannot be saved', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $linked = Course::factory()->create(['program_id' => $program->id]);
    $notInPlan = Course::factory()->create(['program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $linked->id, 'credits' => 4],
            ]],
        ])
        ->set('structurePrerequisites', [
            [
                'key' => "{$linked->id}-{$notInPlan->id}",
                'required_course_id' => $linked->id,
                'dependent_course_id' => $notInPlan->id,
            ],
        ])
        ->call('saveStructure')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    // Blocked, not merely flagged: nothing at all was written for this plan.
    expect(Prerequisite::query()->where('study_plan_id', $plan->id)->count())->toBe(0);
    expect(Level::query()->where('study_plan_id', $plan->id)->count())->toBe(0);
});

it('RC-01 — the number of active students per plan and level is queryable', function (): void {
    $plan = StudyPlan::factory()->create();

    $firstOnLevelOne = Student::factory()->create(['active' => true]);
    $secondOnLevelOne = Student::factory()->create(['active' => true]);
    $onLevelTwo = Student::factory()->create(['active' => true]);
    $inactive = Student::factory()->create(['active' => false]);

    foreach ([[$firstOnLevelOne, 1], [$secondOnLevelOne, 1], [$onLevelTwo, 2], [$inactive, 1]] as [$student, $level]) {
        StudentPlan::query()->create([
            'student_id' => $student->id,
            'study_plan_id' => $plan->id,
            'current_level' => $level,
        ]);
    }

    $counts = app(GetActiveStudentCountsUseCase::class)->handle($plan->id);

    expect($counts[1])->toBe(2);
    expect($counts[2])->toBe(1);
});
