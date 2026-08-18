<?php

declare(strict_types=1);

use App\Support\Performance\InteractionClass;
use App\Support\Performance\InteractionMeasurement;
use Database\Seeders\PerformanceVolumeSeeder;
use Livewire\Livewire;

/**
 * Server-render timing, the soft half of layer 1.
 *
 * Unlike query counts, wall time depends on the machine, so asserting it at the
 * real budget (500 ms for a module open) would make this suite fail on a busy
 * laptop and pass on a quiet one — useless as a verdict. Instead:
 *
 *   - the exact time is ALWAYS recorded and printed, so a regression is visible;
 *   - the assertion runs against a deliberately generous ceiling, which catches
 *     an order-of-magnitude regression without flapping.
 *
 * The real perceived budgets (B-01 to B-07) are the browser layer's job:
 * php artisan perf:measure.
 */

/** Order-of-magnitude guard, not the spec budget. See the docblock above. */
const RENDER_CEILING_MS = 5000;

it('records server render time for every module and stays within an order of magnitude', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $measurements = [];

    foreach (measuredModules() as $module => $config) {
        $user = userWithPermissions($config['permissions']);

        $startedAt = microtime(true);
        $component = Livewire::actingAs($user)->test($config['component'])->assertOk();
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        $measurements[] = InteractionMeasurement::fromServer(
            module: $module,
            interaction: 'open',
            class: InteractionClass::ModuleOpen,
            renderMs: $elapsedMs,
            queryCount: 0,
            serializedRows: serializedRowCount(viewDataOr($component, $config['listKey'])),
        );
    }

    // Always visible, pass or fail — a number nobody can see protects nothing.
    foreach ($measurements as $measurement) {
        fwrite(STDERR, sprintf(
            "  render %-22s %5d ms  (%d rows)\n",
            $measurement->module,
            $measurement->contentReadyMs,
            $measurement->serializedRows,
        ));
    }

    $tooSlow = array_filter(
        $measurements,
        static fn (InteractionMeasurement $m): bool => $m->contentReadyMs > RENDER_CEILING_MS,
    );

    expect(array_map(static fn (InteractionMeasurement $m): string => $m->key(), $tooSlow))->toBe([]);
});

it('takes a percentile rather than trusting a single observation', function (): void {
    // Guards the statistic itself: the harness must not be able to declare
    // success from one lucky run. p95 of 20 sorted observations is the 19th.
    $observations = range(1, 20);

    expect(InteractionMeasurement::percentileOf($observations, 95))->toBe(19)
        ->and(InteractionMeasurement::percentileOf($observations, 50))->toBe(10)
        ->and(InteractionMeasurement::percentileOf($observations, 100))->toBe(20);
});

it('refuses a measurement that observed nothing', function (): void {
    expect(fn () => new InteractionMeasurement(
        module: 'courses',
        interaction: 'open',
        class: InteractionClass::ModuleOpen,
        contentReadyMs: 120,
        layer: InteractionMeasurement::LAYER_BROWSER,
    ))->toThrow(InvalidArgumentException::class, 'observed neither paint nor queries');
});

it('refuses a measurement where content is ready before first paint', function (): void {
    expect(fn () => new InteractionMeasurement(
        module: 'courses',
        interaction: 'open',
        class: InteractionClass::ModuleOpen,
        contentReadyMs: 40,
        layer: InteractionMeasurement::LAYER_BROWSER,
        firstPaintMs: 90,
    ))->toThrow(InvalidArgumentException::class, 'instrumentation error');
});
