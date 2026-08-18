<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Performance\PerformanceBudget;
use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * FR-013 and FR-015 — the report has to be readable by machine and by person,
 * and it has to name the module and interaction behind any failure.
 *
 * The format is a contract
 * (specs/002-perceived-performance/contracts/measurement-report.md) because
 * baseline comparison depends on it: rename a key and SC-008 silently stops
 * comparing anything.
 */
function latestReport(): array
{
    $files = collect(File::files(storage_path('app/performance')))
        ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'report-'))
        ->sortByDesc(fn ($file): int => $file->getMTime())
        ->values();

    expect($files)->not->toBeEmpty();

    return json_decode(File::get($files[0]->getPathname()), true);
}

beforeEach(function (): void {
    (new PerformanceVolumeSeeder)->run();

    $user = User::factory()->create();
    $user->givePermissionTo('courses.view');

    Artisan::call('perf:measure', ['--repetitions' => 1, '--user' => $user->email]);
});

it('produces a report with every contract field present', function (): void {
    $report = latestReport();

    expect($report)->toHaveKeys([
        'takenAt', 'layer', 'repetitions', 'concurrency', 'volume', 'verdict', 'rows', 'notMeasured',
    ])->and($report['verdict'])->toBeIn(['pass', 'fail']);
});

it('never omits notMeasured, even when there is nothing to report', function (): void {
    $report = latestReport();

    // Present as [] rather than absent. A report that is silent about its gaps
    // reads as full coverage, and the point of the field is that it cannot.
    expect($report)->toHaveKey('notMeasured')
        ->and($report['notMeasured'])->toBeArray();
});

it('names the module and interaction on every row', function (): void {
    $report = latestReport();

    expect($report['rows'])->not->toBeEmpty();

    foreach ($report['rows'] as $row) {
        expect($row)->toHaveKeys([
            'module', 'interaction', 'class', 'budget', 'percentile',
            'observedMs', 'maxMs', 'verdict', 'excessPercent',
        ])->and($row['verdict'])->toBeIn(['pass', 'fail']);
    }
});

it('leaves unobserved fields null rather than filling them with zero', function (): void {
    $report = latestReport();

    foreach ($report['rows'] as $row) {
        // The deterministic layer cannot see paint. A zero here would claim it
        // looked and measured none, which is a different and false statement.
        expect($row['firstPaintMs'])->toBeNull()
            ->and($row['queryCount'])->not->toBeNull();
    }
});

it('reports every failing row with its excess over the ceiling', function (): void {
    $report = latestReport();

    foreach ($report['rows'] as $row) {
        if ($row['verdict'] === 'fail') {
            expect($row['excessPercent'])->toBeGreaterThan(0);
        } else {
            expect($row['excessPercent'])->toBe(0);
        }
    }
});

it('records the volume it measured against', function (): void {
    $report = latestReport();

    // SC-007: a measurement on demo data is not evidence. The report has to say
    // what it ran against so a reader can tell the difference.
    expect($report['volume']['courses'])->toBeGreaterThanOrEqual(800)
        ->and($report['volume']['equivalencies'])->toBeGreaterThanOrEqual(500);
});

it('traces every budget back to a spec criterion', function (): void {
    foreach (PerformanceBudget::timeBudgets() as $id => $budget) {
        expect($budget->criterion)->toStartWith('SC-')
            ->and($budget->id)->toBe($id);
    }
});

it('documents why the equivalency write path has no query ceiling', function (): void {
    // S-05's absence is a decision, not an omission, and it is written down so
    // nobody closes the "gap" by capping queries and then caching the graph.
    expect(PerformanceBudget::structuralBudgets())->not->toHaveKey('S-05')
        ->and(PerformanceBudget::structuralNotes())->toContain('stale graph');
});
