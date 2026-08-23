<?php

declare(strict_types=1);

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

it('blocks mounting the component for a user without study_plans.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(StudyPlanComponent::class)->assertForbidden();
});

it('blocks creating a plan for a user without study_plans.create', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->set('form.programId', $program->id)
        ->set('form.name', 'Plan 2024')
        ->set('form.implementationYear', '2024')
        ->set('form.classification', PlanClassification::Active->value)
        ->call('save')
        ->assertForbidden();
});

it('creates an Active plan with no closing date', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->set('form.programId', $program->id)
        ->set('form.name', 'Plan 2024')
        ->set('form.implementationYear', '2024')
        ->set('form.classification', PlanClassification::Active->value)
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    expect(StudyPlan::query()->where('name', 'Plan 2024')->exists())->toBeTrue();
});

it('blocks saving a Terminal plan with no enrollment closing date', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->set('form.programId', $program->id)
        ->set('form.name', 'Terminal Plan 2018')
        ->set('form.implementationYear', '2018')
        ->set('form.classification', PlanClassification::Terminal->value)
        ->set('form.enrollmentClosingDate', null)
        ->call('save')
        ->assertHasErrors(['form.enrollmentClosingDate']);

    expect(StudyPlan::query()->where('name', 'Terminal Plan 2018')->exists())->toBeFalse();
});

it('blocks editing a plan for a user without study_plans.edit', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $plan = StudyPlan::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('openEditModal', $plan->id)
        ->assertForbidden();
});

it('creates a Terminal plan when a closing date is supplied', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->set('form.programId', $program->id)
        ->set('form.name', 'Terminal Plan 2018')
        ->set('form.implementationYear', '2018')
        ->set('form.classification', PlanClassification::Terminal->value)
        ->set('form.enrollmentClosingDate', now()->addYear()->format('Y-m-d'))
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    expect(StudyPlan::query()->where('name', 'Terminal Plan 2018')->exists())->toBeTrue();
});

it('displays a Terminal plan\'s enrollment closing date in the catalog', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->terminal()->create(['program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->assertSee($plan->enrollment_closing_date->format('Y-m-d'));
});

it('persists every editable basic field when a plan is edited, including its program and year', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.create', 'study_plans.edit']);
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $plan = StudyPlan::factory()->create([
        'program_id' => $program->id,
        'implementation_year' => 2018,
        'classification' => PlanClassification::Active,
    ]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('openEditModal', $plan->id)
        ->set('form.programId', $otherProgram->id)
        ->set('form.implementationYear', '2022')
        ->set('form.name', 'Plan 2022 (relocated)')
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->program_id)->toBe($otherProgram->id);
    expect((int) $plan->implementation_year)->toBe(2022);
    expect($plan->name)->toBe('Plan 2022 (relocated)');
});

it('shows a plan\'s full structure: levels, linked courses and prerequisites', function (): void {
    $user = userWithPermissions(['study_plans.view']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);

    $level = Level::factory()->create(['study_plan_id' => $plan->id, 'number' => 1]);
    $courseA = Course::factory()->create(['program_id' => $program->id, 'code' => 'STR-101']);
    $courseB = Course::factory()->create(['program_id' => $program->id, 'code' => 'STR-102']);
    $level->courses()->attach([$courseA->id => ['credits' => 4], $courseB->id => ['credits' => 3]]);

    $plan->prerequisites()->create([
        'required_course_id' => $courseA->id,
        'dependent_course_id' => $courseB->id,
    ]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->assertSee('STR-101')
        ->assertSee('STR-102')
        ->assertSee((string) $level->number);
});

it('blocks a prerequisite whose course is not linked to the plan', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $linkedCourse = Course::factory()->create(['program_id' => $program->id]);
    $unlinkedCourse = Course::factory()->create(['program_id' => $program->id]);

    $expectedMessage = __('A course used in a prerequisite must exist within the same study plan.');

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $linkedCourse->id, 'credits' => 4],
            ]],
        ])
        ->set('structurePrerequisites', [
            ['key' => "{$linkedCourse->id}-{$unlinkedCourse->id}", 'required_course_id' => $linkedCourse->id, 'dependent_course_id' => $unlinkedCourse->id],
        ])
        ->call('saveStructure')
        ->assertHasErrors(['structurePrerequisites'])
        ->assertSee($expectedMessage)
        ->assertDispatched(
            'toast-show',
            dataset: ['variant' => 'danger'],
            slots: ['text' => $expectedMessage],
        );

    expect(Prerequisite::query()->where('study_plan_id', $plan->id)->count())->toBe(0);
});

it('blocks a prerequisite where required and dependent are the same course', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create(['program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $course->id, 'credits' => 4],
            ]],
        ])
        ->set('structurePrerequisites', [
            ['key' => "{$course->id}-{$course->id}", 'required_course_id' => $course->id, 'dependent_course_id' => $course->id],
        ])
        ->call('saveStructure')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect(Prerequisite::query()->where('study_plan_id', $plan->id)->count())->toBe(0);
});

it('saves a valid structure end to end through the component', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $courseA = Course::factory()->create(['program_id' => $program->id]);
    $courseB = Course::factory()->create(['program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $courseA->id, 'credits' => 4],
                ['course_id' => $courseB->id, 'credits' => 3],
            ]],
        ])
        ->set('structurePrerequisites', [
            ['key' => "{$courseA->id}-{$courseB->id}", 'required_course_id' => $courseA->id, 'dependent_course_id' => $courseB->id],
        ])
        ->call('saveStructure')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    expect(Level::query()->where('study_plan_id', $plan->id)->count())->toBe(1);
    expect(Prerequisite::query()->where('study_plan_id', $plan->id)->count())->toBe(1);

    $level = Level::query()->where('study_plan_id', $plan->id)->first();
    expect($level->courses()->count())->toBe(2);
});

it('auto-numbers each newly added level from the highest existing number', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id);

    expect($component->get('structureLevels'))->toBe([]);

    $component->call('addLevel')->call('addLevel');

    $levels = $component->get('structureLevels');
    expect(array_column($levels, 'number'))->toBe([1, 2]);
    expect($component->get('activeLevelKey'))->toBe($levels[1]['key']);
});

it('moves the pager to the first remaining level when the active one is removed', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->call('addLevel')
        ->call('addLevel');

    $secondLevelKey = $component->get('structureLevels')[1]['key'];

    $component->call('goToLevel', $secondLevelKey)
        ->call('removeLevel', $secondLevelKey);

    $remaining = $component->get('structureLevels');
    expect($remaining)->toHaveCount(1);
    expect($component->get('activeLevelKey'))->toBe($remaining[0]['key']);
});

it('adds and removes a course prerequisite through the inline per-course panel', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $courseA = Course::factory()->create(['program_id' => $program->id]);
    $courseB = Course::factory()->create(['program_id' => $program->id]);

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $courseA->id, 'credits' => 4],
                ['course_id' => $courseB->id, 'credits' => 3],
            ]],
        ])
        ->call('togglePrerequisitesFor', $courseB->id);

    expect($component->get('expandedPrerequisiteCourseId'))->toBe($courseB->id);

    $component->call('addPrerequisiteFor', $courseB->id, $courseA->id);

    $prerequisites = $component->get('structurePrerequisites');
    expect($prerequisites)->toHaveCount(1);
    expect($prerequisites[0]['required_course_id'])->toBe($courseA->id);
    expect($prerequisites[0]['dependent_course_id'])->toBe($courseB->id);

    $component->call('removePrerequisite', "{$courseA->id}-{$courseB->id}");
    expect($component->get('structurePrerequisites'))->toBe([]);
});

it('rejects a course being set as its own prerequisite from the inline panel', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create(['program_id' => $program->id]);

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => [
                ['course_id' => $course->id, 'credits' => 4],
            ]],
        ])
        ->call('addPrerequisiteFor', $course->id, $course->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($component->get('structurePrerequisites'))->toBe([]);
});

it('rejects a non-numeric or zero credits value when adding a course to a level', function (): void {
    $user = userWithPermissions(['study_plans.view', 'study_plans.edit']);
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create(['program_id' => $program->id]);

    $component = Livewire::actingAs($user)
        ->test(StudyPlanComponent::class)
        ->call('viewStructure', $plan->id)
        ->set('structureLevels', [
            ['key' => 'lvl-1', 'id' => null, 'number' => 1, 'courses' => []],
        ])
        ->set('newCourseSelection.lvl-1.course_id', $course->id)
        ->set('newCourseSelection.lvl-1.credits', 'abc')
        ->call('addCourseToLevel', 'lvl-1')
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect($component->get('structureLevels')[0]['courses'])->toBe([]);
});

it('returns the correct count of currently active students per plan and level', function (): void {
    $program = Program::factory()->create();
    $plan = StudyPlan::factory()->create(['program_id' => $program->id]);

    $activeLevel1 = Student::factory()->create(['active' => true]);
    $activeLevel1b = Student::factory()->create(['active' => true]);
    $activeLevel2 = Student::factory()->create(['active' => true]);
    $inactiveLevel1 = Student::factory()->create(['active' => false]);

    StudentPlan::query()->create(['student_id' => $activeLevel1->id, 'study_plan_id' => $plan->id, 'current_level' => 1]);
    StudentPlan::query()->create(['student_id' => $activeLevel1b->id, 'study_plan_id' => $plan->id, 'current_level' => 1]);
    StudentPlan::query()->create(['student_id' => $activeLevel2->id, 'study_plan_id' => $plan->id, 'current_level' => 2]);
    StudentPlan::query()->create(['student_id' => $inactiveLevel1->id, 'study_plan_id' => $plan->id, 'current_level' => 1]);

    $counts = app(GetActiveStudentCountsUseCase::class)->handle($plan->id);

    expect($counts[1])->toBe(2);
    expect($counts[2])->toBe(1);
});
