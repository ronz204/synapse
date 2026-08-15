<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Document;
use App\Models\Modality;
use App\Models\ModalityResolution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityAssignmentComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

function modalityPdfUpload(string $name = 'resolution.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

it('blocks mounting the component for a user without modality_resolutions.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(ModalityAssignmentComponent::class)->assertForbidden();
});

it('blocks assigning for a user without modality_resolutions.create', function (): void {
    $user = userWithPermissions(['modality_resolutions.view']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->create();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->call('assign')
        ->assertForbidden();
});

it('rejects assigning a modality that requires a resolution when none is on file', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->call('assign')
        ->assertDispatched('toast', variant: 'danger', text: 'No valid modality resolution exists for this course.');

    expect($course->fresh()->modality_id)->not->toBe($modality->id);
});

it('rejects filing a resolution without an attached document', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.approvingBody', 'Consejo Universitario')
        ->set('form.validFrom', now()->subDay()->toDateString())
        ->call('assign')
        ->assertHasErrors(['form.document']);

    expect(ModalityResolution::query()->where('resolution_number', 'R-1')->exists())->toBeFalse();
});

it('files a currently-valid resolution and assigns the modality in the same submission', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.approvingBody', 'Consejo Universitario')
        ->set('form.validFrom', now()->subDay()->toDateString())
        ->set('form.document', modalityPdfUpload())
        ->call('assign')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'success');

    expect($course->fresh()->modality_id)->toBe($modality->id);

    $resolution = ModalityResolution::query()->where('resolution_number', 'R-1')->firstOrFail();
    expect(Document::query()->where('documentable_type', ModalityResolution::class)->where('documentable_id', $resolution->id)->exists())->toBeTrue();
});

it('assigns successfully by reusing a currently-valid resolution already on file, without filing a new one', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();
    ModalityResolution::factory()->create([
        'course_id' => $course->id,
        'modality_id' => $modality->id,
        'valid_from' => now()->subMonth(),
        'valid_to' => null,
    ]);

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->call('assign')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'success');

    expect($course->fresh()->modality_id)->toBe($modality->id);
});

it('rejects assigning when the only resolution on file has already expired', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();
    ModalityResolution::factory()->expired()->create([
        'course_id' => $course->id,
        'modality_id' => $modality->id,
    ]);

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->call('assign')
        ->assertDispatched('toast', variant: 'danger', text: 'No valid modality resolution exists for this course.');

    expect($course->fresh()->modality_id)->not->toBe($modality->id);
});

it('assigns a modality that does not require a resolution with no resolution fields at all', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->create(); // requires_resolution: false

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->call('assign')
        ->assertOk()
        ->assertHasNoErrors();

    expect($course->fresh()->modality_id)->toBe($modality->id);
});

it('streams the attached resolution document back for a user who can view the assignment listing', function (): void {
    $user = userWithPermissions(['modality_resolutions.view', 'modality_resolutions.create']);
    $course = Course::factory()->create();
    $modality = Modality::factory()->requiresResolution()->create();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->set('form.courseId', $course->id)
        ->set('form.modalityId', $modality->id)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.approvingBody', 'Consejo Universitario')
        ->set('form.validFrom', now()->subDay()->toDateString())
        ->set('form.document', modalityPdfUpload('resolution.pdf'))
        ->call('assign');

    $resolution = ModalityResolution::query()->where('resolution_number', 'R-1')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ModalityAssignmentComponent::class)
        ->call('downloadDocument', $resolution->id)
        ->assertOk();
});
