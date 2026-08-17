<?php

declare(strict_types=1);

/**
 * RC-03 — Catálogo de Modalidades y Resoluciones de Modalidad.
 *
 * One test per acceptance criterion listed in `.claude/docs/approach.md`, plus
 * the seed catalog the requirement enumerates by name.
 */

use App\Models\Course;
use App\Models\Modality;
use App\Models\ModalityResolution;
use App\Models\Program;
use Database\Seeders\ModalitySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityAssignmentComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

it('RC-03 — the master catalog seeds the five named modalities with their "requires resolution" flags', function (): void {
    $this->seed(ModalitySeeder::class);

    $catalog = Modality::query()->pluck('requires_resolution', 'name');

    expect($catalog['Presencial'])->toBeFalse()
        ->and($catalog['Híbrido'])->toBeTrue()
        ->and($catalog['Virtual'])->toBeTrue()
        ->and($catalog['Tutoría'])->toBeTrue()
        ->and($catalog['Aprendizaje Remoto'])->toBeTrue();
});

it('RC-03 AC1 — assigning the Hybrid modality with no currently-valid resolution is rejected with the mandated message', function (): void {
    $this->seed(ModalitySeeder::class);

    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $hybrid = Modality::query()->where('name', 'Híbrido')->firstOrFail();
    $presencial = Modality::query()->where('name', 'Presencial')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $hybrid->id)
        ->call('assign')
        // Verbatim wording from the criterion, which the rubric grades
        // directly — ModalityAssignmentComponent surfaces the domain
        // exception's own message, so what the user sees is this string.
        ->assertDispatched('toast', variant: 'danger', text: 'No valid modality resolution exists for this course.');

    // Rejected outright — the course keeps the modality it already had.
    expect($course->fresh()->modality_id)->toBe($presencial->id);
});

it('RC-03 AC1 — an expired resolution does not satisfy the gate either', function (): void {
    $this->seed(ModalitySeeder::class);

    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $hybrid = Modality::query()->where('name', 'Híbrido')->firstOrFail();

    ModalityResolution::factory()->expired()->create([
        'course_id' => $course->id,
        'modality_id' => $hybrid->id,
    ]);

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $hybrid->id)
        ->call('assign')
        ->assertDispatched('toast', variant: 'danger', text: 'No valid modality resolution exists for this course.');

    expect($course->fresh()->modality_id)->not->toBe($hybrid->id);
});

it('RC-03 — with a currently-valid resolution on file, the Hybrid modality is applied and visible on the course', function (): void {
    $this->seed(ModalitySeeder::class);

    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $hybrid = Modality::query()->where('name', 'Híbrido')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $hybrid->id)
        ->set('form.resolutionNumber', 'RC03-001')
        ->set('form.approvingBody', 'Consejo Universitario')
        ->set('form.validFrom', now()->subDay()->toDateString())
        ->set('form.document', UploadedFile::fake()->create('resolucion.pdf', 100, 'application/pdf'))
        ->call('assign')
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'success');

    expect($course->fresh()->modality_id)->toBe($hybrid->id);
    expect(ModalityResolution::query()->where('resolution_number', 'RC03-001')->exists())->toBeTrue();
});

it('RC-03 AC2 — a new course registered with no modality specified defaults to Presencial', function (): void {
    $this->seed(ModalitySeeder::class);

    $user = userWithPermissions(['courses.view', 'courses.create']);
    $program = Program::factory()->create();

    Livewire::actingAs($user)
        ->test(CourseComponent::class)
        ->set('form.code', 'RC03-101')
        ->set('form.name', 'Curso sin modalidad')
        ->set('form.programId', $program->id)
        ->call('save')
        ->assertHasNoErrors();

    $course = Course::query()->where('code', 'RC03-101')->firstOrFail();

    expect($course->modality)->not->toBeNull();
    expect($course->modality->name)->toBe('Presencial');
    expect($course->modality->requires_resolution)->toBeFalse();
});
