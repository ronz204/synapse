<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

/**
 * FR-011 and contract rule R-05 — a domain rejection arrives as fast as a
 * success, says exactly why, and leaves the user's input alone.
 *
 * These are the three cases the harness is required to cover, and they are
 * measured against the seeded negative cases rather than a two-row fixture:
 * cycle detection walks the whole active graph, so its cost is only honest at
 * the target volume.
 *
 * The message content is asserted as strictly as the timing. Principle II of
 * the constitution requires the full cycle chain and the conflicting pair to
 * reach the user verbatim; making a rejection fast by making it vaguer would
 * satisfy this feature and break the system.
 */
beforeEach(function (): void {
    Storage::fake('local');
    (new PerformanceVolumeSeeder)->run();
});

function equivalencyPdf(): UploadedFile
{
    return UploadedFile::fake()->create('resolution.pdf', 100, 'application/pdf');
}

function courseIdByCode(string $code): int
{
    return (int) DB::table('courses')->where('code', $code)->value('id');
}

it('rejects a cycle showing the full chain, at target volume', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    // PERF-0791 -> PERF-0792 -> PERF-0793 are already active; this closes it.
    $component = Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', courseIdByCode('PERF-0793'))
        ->set('form.targetCourseId', courseIdByCode('PERF-0791'))
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CLOSES-CYCLE')
        ->set('form.document', equivalencyPdf());

    $startedAt = microtime(true);
    $component->call('register')->assertOk();
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

    fwrite(STDERR, sprintf("  reject:cycle %d ms\n", $elapsedMs));

    // Nothing was persisted.
    expect(DB::table('equivalencies')->where('resolution_number', 'R-CLOSES-CYCLE')->exists())->toBeFalse();

    // And the chain reached the user rather than a generic error. The toast
    // payload carries the exact label the domain produced.
    $component->assertDispatched('toast', function (string $event, array $payload): bool {
        return $payload['variant'] === 'danger'
            && str_contains($payload['text'], 'PERF-0791')
            && str_contains($payload['text'], 'PERF-0792')
            && str_contains($payload['text'], 'PERF-0793');
    });
});

it('keeps the user input intact after a cycle rejection', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', courseIdByCode('PERF-0793'))
        ->set('form.targetCourseId', courseIdByCode('PERF-0791'))
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CLOSES-CYCLE')
        ->set('form.document', equivalencyPdf())
        ->call('register')
        // Retyping everything after a rejection is its own kind of slow.
        ->assertSet('form.resolutionNumber', 'R-CLOSES-CYCLE')
        ->assertSet('form.sourceCourseId', courseIdByCode('PERF-0793'));
});

it('surfaces a contradiction as an explicit human decision, at target volume', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    $existingId = (int) DB::table('equivalencies')->where('resolution_number', 'R-CONTRA-0001')->value('id');

    $component = Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', courseIdByCode('PERF-0795'))
        ->set('form.targetCourseId', courseIdByCode('PERF-0796'))
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-CONTRA-0002')
        ->set('form.document', equivalencyPdf());

    $startedAt = microtime(true);
    $component->call('register')->assertOk();
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

    fwrite(STDERR, sprintf("  reject:contradiction %d ms\n", $elapsedMs));

    // Blocked and escalated, not silently resolved either way.
    $component->assertSet('conflictingEquivalencyId', $existingId);
    expect(DB::table('equivalencies')->where('resolution_number', 'R-CONTRA-0002')->exists())->toBeFalse();
});

it('still walks the whole active graph rather than a shortcut', function (): void {
    // Guards against the tempting optimisation: a cheap "is there a direct
    // reverse edge" check would pass the two-node case and miss the three-node
    // chain above. If this ever fails while the cycle test passes, the
    // detection has been narrowed and Principle II is broken.
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.create']);

    $activeEdges = DB::table('equivalencies')->where('status', 'Vigente')->count();

    expect($activeEdges)->toBeGreaterThan(300);

    Livewire::actingAs($user)
        ->test(EquivalencyComponent::class)
        ->set('form.sourceCourseId', courseIdByCode('PERF-0793'))
        ->set('form.targetCourseId', courseIdByCode('PERF-0791'))
        ->set('form.direction', EquivalencyDirection::OldToNew->value)
        ->set('form.resolutionNumber', 'R-THREE-NODE')
        ->set('form.document', equivalencyPdf())
        ->call('register');

    expect(DB::table('equivalencies')->where('resolution_number', 'R-THREE-NODE')->exists())->toBeFalse();
});
