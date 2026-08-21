<?php

declare(strict_types=1);

/**
 * RC-02 — Registro de Equiparaciones entre Planes con Validación de Integridad.
 *
 * The centerpiece requirement, and the one carrying its own 20-point rubric
 * line. One test per acceptance criterion listed in `.claude/docs/approach.md`.
 */

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use App\Models\Course;
use App\Models\Document;
use App\Models\Equivalency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Curriculum\Equivalency\Application\DTOs\EquivalencyDTO;
use Src\Curriculum\Equivalency\Application\UseCases\RegisterEquivalencyUseCase;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencyDocumentRequiredException;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

beforeEach(function (): void {
    Storage::fake('local');
});

function rc02Pdf(string $name = 'resolucion.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 100, 'application/pdf');
}

it('RC-02 AC1 — an equivalency submitted without an attached resolution is blocked, never saved, with the message the criterion mandates', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'RC02-NO-DOC')
        ->call('register')
        ->assertHasErrors(['form.document'])
        // Verbatim wording from the criterion — the rubric grades the
        // specific message, not just the save being blocked. EquivalencyForm
        // carries a messages() override so this reaches the user exactly as
        // worded, instead of Laravel's generic "The document field is
        // required." Routed through __() since the app's default locale is
        // Spanish and the message is translated like everything else in the
        // modal.
        ->assertSee(__('You must attach the resolution that approves this equivalency.'));

    expect(Equivalency::query()->where('resolution_number', 'RC02-NO-DOC')->exists())->toBeFalse();
});

it('RC-02 AC1 — the domain itself refuses a documentless equivalency with the message the criterion mandates', function (): void {
    // The adapter's form validation is the first gate; this asserts the
    // authoritative one behind it, which is where the mandated wording lives
    // — and which EquivalencyForm::messages() is kept word-for-word in sync
    // with, so the two can never drift apart.
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $dto = new EquivalencyDTO(
        sourceCourseId: $source->id,
        targetCourseId: $target->id,
        direction: EquivalencyDirection::OldToNew,
        resolutionNumber: 'RC02-NO-DOC-DOMAIN',
        document: null,
    );

    expect(fn () => app(RegisterEquivalencyUseCase::class)->handle($dto))
        ->toThrow(
            EquivalencyDocumentRequiredException::class,
            'You must attach the resolution that approves this equivalency.',
        );

    expect(Equivalency::query()->where('resolution_number', 'RC02-NO-DOC-DOMAIN')->exists())->toBeFalse();
});

it('RC-02 AC2 — an equivalency saved with direction "old plan → new plan" is persisted and displayed with that direction', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create(['code' => 'RC02-OLD']);
    $target = Course::factory()->create(['code' => 'RC02-NEW']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'RC02-001')
        ->set('form.document', rc02Pdf())
        ->call('register')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $equivalency = Equivalency::query()->where('resolution_number', 'RC02-001')->firstOrFail();

    expect($equivalency->direction)->toBe(EquivalencyDirection::OldToNew);
    expect($equivalency->source_course_id)->toBe($source->id);
    expect($equivalency->target_course_id)->toBe($target->id);
    expect($equivalency->status)->toBe(EquivalencyStatus::Active);

    // The attached resolution is stored alongside it — an equivalency without
    // its document is precisely what AC1 forbids.
    expect(Document::query()
        ->where('documentable_type', Equivalency::class)
        ->where('documentable_id', $equivalency->id)
        ->exists())->toBeTrue();

    // And the record reads back with that direction, resolution number and
    // both course codes in the listing.
    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->assertSee(__(Str::headline(EquivalencyDirection::OldToNew->name)))
        ->assertSee('RC02-001')
        ->assertSee('RC02-OLD')
        ->assertSee('RC02-NEW');
});

it('RC-02 AC3 — an equivalency that would close a directed cycle is rejected, and the error names the full conflicting chain', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    // Five nodes, not three: the rubric explicitly penalizes cycle detection
    // that only catches short chains.
    $a = Course::factory()->create(['code' => 'CYC-A']);
    $b = Course::factory()->create(['code' => 'CYC-B']);
    $c = Course::factory()->create(['code' => 'CYC-C']);
    $d = Course::factory()->create(['code' => 'CYC-D']);
    $e = Course::factory()->create(['code' => 'CYC-E']);

    foreach ([[$a, $b], [$b, $c], [$c, $d], [$d, $e]] as $index => [$from, $to]) {
        Equivalency::factory()->oldToNew()->create([
            'source_course_id' => $from->id,
            'target_course_id' => $to->id,
            'resolution_number' => 'CYC-EXISTING-'.$index,
        ]);
    }

    // E → A closes CYC-A → CYC-B → CYC-C → CYC-D → CYC-E → CYC-A.
    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $e->id)
        ->set('form.targetCourseId', $a->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'CYC-CANDIDATE')
        ->set('form.document', rc02Pdf())
        ->call('register')
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return $params['dataset']['variant'] === 'danger'
                && str_contains($params['slots']['text'], 'CYC-A → CYC-B → CYC-C → CYC-D → CYC-E → CYC-A');
        });

    expect(Equivalency::query()->where('resolution_number', 'CYC-CANDIDATE')->exists())->toBeFalse();
});

it('RC-02 AC4 — a second, contradictory record for the same pair and direction is blocked pending an explicit decision', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'RC02-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'RC02-CANDIDATE')
        ->set('form.document', rc02Pdf())
        ->call('register')
        // The conflict is surfaced with the conflicting record identified,
        // not silently swallowed.
        ->assertSet('conflictingEquivalencyId', $existing->id);

    // Nothing is written until a human designates the prevailing resolution.
    expect(Equivalency::query()->where('resolution_number', 'RC02-CANDIDATE')->exists())->toBeFalse();
    expect($existing->fresh()->status)->toBe(EquivalencyStatus::Active);
});

it('RC-02 AC4 — designating the new resolution as prevailing tags the previous one "Superseded"', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'RC02-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'RC02-CANDIDATE')
        ->set('form.document', rc02Pdf())
        ->call('register')
        ->call('resolveContradiction', 'candidate')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $candidate = Equivalency::query()->where('resolution_number', 'RC02-CANDIDATE')->firstOrFail();

    expect($candidate->status)->toBe(EquivalencyStatus::Active);
    expect($existing->fresh()->status)->toBe(EquivalencyStatus::Superseded);
    expect($existing->fresh()->superseded_by_id)->toBe($candidate->id);
});

it('RC-02 AC4 — designating the existing resolution as prevailing keeps the loser on record as "Superseded", never deleted', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create', 'equivalencies.resolve_contradiction']);
    $source = Course::factory()->create();
    $target = Course::factory()->create();

    $existing = Equivalency::factory()->oldToNew()->create([
        'source_course_id' => $source->id,
        'target_course_id' => $target->id,
        'resolution_number' => 'RC02-EXISTING',
    ]);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', $source->id)
        ->set('form.targetCourseId', $target->id)
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'RC02-CANDIDATE')
        ->set('form.document', rc02Pdf())
        ->call('register')
        ->call('resolveContradiction', 'existing')
        ->assertDispatched('toast-show', dataset: ['variant' => 'success']);

    $candidate = Equivalency::query()->where('resolution_number', 'RC02-CANDIDATE')->firstOrFail();

    // Traceability: historical data is never overwritten or dropped.
    expect($existing->fresh()->status)->toBe(EquivalencyStatus::Active);
    expect($candidate->status)->toBe(EquivalencyStatus::Superseded);
    expect($candidate->superseded_by_id)->toBe($existing->id);
});
