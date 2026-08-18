<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Program;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

/**
 * FR-004 and contract rule R-04 — an action in flight cannot be fired twice.
 *
 * Two halves, and both are needed. Livewire already discards overlapping
 * requests from the same component, so the database was never really at risk;
 * what FR-004 actually asks for is that the user not be left looking at an
 * enabled button that quietly does nothing. So one test covers the data and one
 * covers what the user can see.
 */
it('creates a single record when the same action is fired twice', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.create']);
    $program = Program::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'DUP-001')
        ->set('form.name', 'Duplicate guard')
        ->set('form.programId', $program->id);

    $component->call('save');
    $component->call('save');

    expect(Course::query()->where('code', 'DUP-001')->count())->toBe(1);
});

it('disables every write button while its own action is running', function (): void {
    $views = [
        'curriculum/course/livewire/course-component',
        'curriculum/equivalency/livewire/equivalency-component',
        'curriculum/modality/livewire/modality-assignment-component',
        'curriculum/modality/livewire/modality-component',
        'curriculum/study-plan/livewire/study-plan-component',
        'curriculum/study-plan/livewire/study-plan-structure',
        'identityaccess/permission/livewire/permission-component',
        'identityaccess/role/livewire/role-component',
    ];

    $unguarded = [];

    foreach ($views as $view) {
        $contents = File::get(base_path("resources/views/{$view}.blade.php"));

        preg_match_all('/wire:click="(save|register|assign|saveStructure|resolveContradiction)[^"]*"(?![^>]*wire:loading\.attr)/', $contents, $matches);

        foreach ($matches[1] as $action) {
            $unguarded[] = "{$view} → {$action}";
        }
    }

    expect($unguarded)->toBe([], "Write actions with no disabled-while-running guard:\n".implode("\n", $unguarded));
});

it('scopes each guard to its own action', function (): void {
    $course = File::get(base_path('resources/views/curriculum/course/livewire/course-component.blade.php'));

    // wire:target matters as much as wire:loading: an unscoped guard would grey
    // out the save button while an unrelated export ran, which reads as the
    // page breaking rather than as the action being taken.
    expect($course)->toContain('wire:loading.attr="disabled" wire:target="save"');
});
