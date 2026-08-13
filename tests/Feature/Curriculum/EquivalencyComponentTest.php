<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Document;
use App\Models\Equivalency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

function pdfUpload(string $name = 'resolution.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

it('blocks mounting the component for a user without equivalencies.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(EquivalencyComponent::class)->assertForbidden();
});

it('blocks registering for a user without equivalencies.create', function (): void {
    $user = userWithPermissions(['equivalencies.view']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->assertForbidden();
});

it('blocks saving without an attached resolution document', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-1')
        ->call('register')
        ->assertHasErrors(['form.document']);

    expect(Equivalency::query()->count())->toBe(0);
});

it('registers an equivalency with the OldToNew direction and persists it exactly as submitted', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'success');

    $equivalency = Equivalency::query()->where('resolution_number', 'R-1')->firstOrFail();

    expect($equivalency->direction)->toBe(EquivalencyDirection::OldToNew);
    expect($equivalency->status)->toBe(EquivalencyStatus::Active);
    expect(Document::query()->where('documentable_type', Equivalency::class)->where('documentable_id', $equivalency->id)->exists())->toBeTrue();
});

it('rejects a new equivalency that would close a directed cycle spanning more than three courses', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    $a = Course::factory()->create();
    $b = Course::factory()->create();
    $c = Course::factory()->create();
    $d = Course::factory()->create();
    $e = Course::factory()->create();

    Equivalency::factory()->oldToNew()->create(['source_course_id' => $a->id, 'target_course_id' => $b->id, 'resolution_number' => 'R-A-B']);
    Equivalency::factory()->oldToNew()->create(['source_course_id' => $b->id, 'target_course_id' => $c->id, 'resolution_number' => 'R-B-C']);
    Equivalency::factory()->oldToNew()->create(['source_course_id' => $c->id, 'target_course_id' => $d->id, 'resolution_number' => 'R-C-D']);
    Equivalency::factory()->oldToNew()->create(['source_course_id' => $d->id, 'target_course_id' => $e->id, 'resolution_number' => 'R-D-E']);

    // E -> A would close A -> B -> C -> D -> E -> A.
    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $e->id)
        ->set('form.targetCourseId', $a->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-E-A')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->assertDispatched('toast', variant: 'danger');

    expect(Equivalency::query()->where('resolution_number', 'R-E-A')->exists())->toBeFalse();
});

it('does not let a Superseded equivalency block a new, otherwise-unrelated cycle-shaped chain', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    $a = Course::factory()->create();
    $b = Course::factory()->create();
    $c = Course::factory()->create();

    Equivalency::factory()->oldToNew()->create(['source_course_id' => $a->id, 'target_course_id' => $b->id, 'resolution_number' => 'R-A-B']);
    // The edge that would complete the cycle is Superseded, not Active.
    Equivalency::factory()->oldToNew()->superseded()->create(['source_course_id' => $c->id, 'target_course_id' => $a->id, 'resolution_number' => 'R-C-A-OLD']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $b->id)
        ->set('form.targetCourseId', $c->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-B-C')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->assertOk()
        ->assertHasNoErrors()
        ->assertDispatched('toast', variant: 'success');

    expect(Equivalency::query()->where('resolution_number', 'R-B-C')->exists())->toBeTrue();
});

it('blocks a second submission for an already-active triple and surfaces the conflict panel', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->assertSet('conflictingEquivalencyId', $existing->id);

    // Blocked, and not silently discarded either — nothing persisted yet
    // until the human explicitly designates a winner.
    expect(Equivalency::query()->where('resolution_number', 'R-CANDIDATE')->exists())->toBeFalse();
});

it('resolves a contradiction in favor of the candidate: existing becomes Superseded, candidate becomes Active', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->call('resolveContradiction', 'candidate')
        ->assertOk()
        ->assertDispatched('toast', variant: 'success');

    $existing->refresh();
    $candidate = Equivalency::query()->where('resolution_number', 'R-CANDIDATE')->firstOrFail();

    expect($existing->status)->toBe(EquivalencyStatus::Superseded);
    expect($existing->superseded_by_id)->toBe($candidate->id);
    expect($candidate->status)->toBe(EquivalencyStatus::Active);
});

it('resolves a contradiction in favor of the existing record: candidate is persisted as Superseded, never discarded', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->call('resolveContradiction', 'existing')
        ->assertOk()
        ->assertDispatched('toast', variant: 'success');

    $existing->refresh();
    $candidate = Equivalency::query()->where('resolution_number', 'R-CANDIDATE')->firstOrFail();

    expect($existing->status)->toBe(EquivalencyStatus::Active);
    expect($candidate->status)->toBe(EquivalencyStatus::Superseded);
    expect($candidate->superseded_by_id)->toBe($existing->id);
    expect(Document::query()->where('documentable_type', Equivalency::class)->where('documentable_id', $candidate->id)->exists())->toBeTrue();
});

it('blocks resolving a contradiction for a user without equivalencies.resolve_contradiction', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->call('resolveContradiction', 'candidate')
        ->assertForbidden();
});

it('streams the attached resolution document back for a user who can view the equivalency', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-1')
        ->set('form.document', pdfUpload('resolution.pdf'))
        ->call('register');

    $equivalency = Equivalency::query()->where('resolution_number', 'R-1')->firstOrFail();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->call('downloadDocument', $equivalency->id)
        ->assertOk();
});

// A separate "blocks downloading without permission" test isn't
// constructible: EquivalencyPolicy::view() and viewAny() both check the same
// equivalencies.view permission, so a user lacking it is already forbidden
// at mount() — the "blocks mounting..." test above already covers that gate.

it('surfaces the superseding resolution number on a Superseded row', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'R-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CANDIDATE')
        ->set('form.document', pdfUpload())
        ->call('register')
        ->call('resolveContradiction', 'candidate');

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->assertSee($existing->fresh()->resolution_number)
        ->assertSee('R-CANDIDATE');
});
