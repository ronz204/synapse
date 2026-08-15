<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Modality;
use App\Models\Program;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

it('blocks mounting the component for a user without courses.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(CourseComponent::class)->assertForbidden();
});

it('blocks creating a course for a user without courses.create', function (): void {
    $user = userWithPermissions(['courses.view']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'INF-101')
        ->set('form.name', 'Programming I')
        ->set('form.programId', $program->id)
        ->call('save')
        ->assertForbidden();
});

it('creates a course for a user holding courses.create', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'INF-101')
        ->set('form.name', 'Programming I')
        ->set('form.programId', $program->id)
        ->call('save')
        ->assertOk();

    expect(Course::query()->where('code', 'INF-101')->exists())->toBeTrue();
});

it('blocks saving a non-service course with no program', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'INF-102')
        ->set('form.name', 'Programming II')
        ->set('form.isService', false)
        ->set('form.programId', null)
        ->call('save')
        ->assertHasErrors(['form.programId']);

    expect(Course::query()->where('code', 'INF-102')->exists())->toBeFalse();
});

it('allows saving a service course with no program', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'ENG-101')
        ->set('form.name', 'English I')
        ->set('form.isService', true)
        ->set('form.programId', null)
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    $course = Course::query()->where('code', 'ENG-101')->first();
    expect($course)->not->toBeNull();
    expect($course->program_id)->toBeNull();
});

it('blocks a duplicate course code', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);
    $program = Program::factory()->create();
    Course::factory()->create(['code' => 'INF-101', 'program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'INF-101')
        ->set('form.name', 'Another course')
        ->set('form.programId', $program->id)
        ->call('save')
        ->assertHasErrors(['form.code']);
});

it('defaults a course with no modality specified to Presencial', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'INF-103')
        ->set('form.name', 'Programming III')
        ->set('form.programId', $program->id)
        ->call('save')
        ->assertOk();

    $course = Course::query()->where('code', 'INF-103')->first();
    expect($course->modality->name)->toBe('Presencial');
});

it('blocks deactivating a course for a user without courses.delete', function (): void {
    $user = userWithPermissions(['courses.view']);
    $program = Program::factory()->create();
    $course = Course::factory()->create(['program_id' => $program->id]);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('delete', $course->id)
        ->assertForbidden();
});

it('deactivates rather than deletes a course row', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.delete']);
    $program = Program::factory()->create();
    $course = Course::factory()->create(['program_id' => $program->id, 'active' => true]);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('delete', $course->id)
        ->assertOk();

    expect(Course::query()->whereKey($course->id)->exists())->toBeTrue();
    expect($course->fresh()->active)->toBeFalse();
});

it('never changes a course\'s modality through the edit modal (RC-03 gate cannot be bypassed via courses.edit)', function (): void {
    // CourseForm no longer exposes a modality field at all — reassigning a
    // course's modality is exclusively AssignModalityToCourseUseCase's job
    // (RC-03), which runs the write-time resolution gate. Editing a course
    // here must leave modality_id untouched no matter what else changes.
    $user = userWithPermissions(['courses.view', 'courses.create', 'courses.edit']);
    $program = Program::factory()->create();
    $modality = Modality::factory()->create(['name' => 'Virtual']);
    $course = Course::factory()->create(['program_id' => $program->id, 'modality_id' => $modality->id]);

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->call('openEditModal', $course->id)
        ->set('form.name', 'Programming IV (renamed)')
        ->call('save')
        ->assertOk();

    $course->refresh();
    expect($course->name)->toBe('Programming IV (renamed)');
    expect($course->modality_id)->toBe($modality->id);
});
