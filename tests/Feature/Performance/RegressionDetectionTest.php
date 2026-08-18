<?php

declare(strict_types=1);

use App\Support\Performance\PerformanceBudget;
use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

/**
 * The test of the tests.
 *
 * A harness that passes is only worth something if it can be shown to fail for
 * the right reason. Each case here reintroduces one of the regressions this
 * feature removed and asserts the budget catches it — so a future change that
 * quietly breaks the harness itself gets caught here rather than by nobody.
 */
beforeEach(function (): void {
    (new PerformanceVolumeSeeder)->run();
});

/**
 * CourseComponent as it behaved before this feature: the whole catalog into
 * every payload.
 *
 * A subclass rather than reflection on a live instance, because $tableMode is
 * protected and therefore re-read from the class on every Livewire hydration —
 * poking the instance would be undone on the next round trip and the test would
 * pass for the wrong reason.
 */
class ClientModeCourseComponent extends CourseComponent
{
    protected string $tableMode = 'client';
}

it('catches a large catalog being moved back to client mode (S-04)', function (): void {
    $user = userWithPermissions(['courses.view', 'courses.search']);

    $component = Livewire::actingAs($user)->test(ClientModeCourseComponent::class)->assertOk();

    $rows = count($component->viewData('rows'));
    $ceiling = PerformanceBudget::structuralBudgets()['S-04'];

    // 800 rows against a 200 ceiling. This is the number QueryBudgetTest's S-04
    // case reports when someone flips a large catalog back.
    expect($rows)->toBeGreaterThan($ceiling);
});

it('catches the equivalency listing returning to a per-row lookup (S-01)', function (): void {
    $user = userWithPermissions(['equivalencies.view', 'equivalencies.search']);

    $perPageQueries = 0;
    DB::listen(function () use (&$perPageQueries): void {
        $perPageQueries++;
    });

    Livewire::actingAs($user)->test(EquivalencyComponent::class)->assertOk();

    $ceiling = PerformanceBudget::structuralBudgets()['S-01'];

    // The state this feature fixed measured 657 queries here. If a change ever
    // reinstates the per-row lookup, this is the number that moves.
    expect($perPageQueries)->toBeLessThanOrEqual($ceiling);
});

it('would fail if a budget were quietly raised to make a number fit', function (): void {
    // Budgets descend from spec criteria; changing one is changing the spec.
    // Pinning the values here means a silent edit to PerformanceBudget shows up
    // as a failing test rather than as a suddenly-green report.
    $budgets = PerformanceBudget::timeBudgets();

    expect($budgets['B-01']->maxMilliseconds)->toBe(100)
        ->and($budgets['B-02']->maxMilliseconds)->toBe(500)
        ->and($budgets['B-03']->maxMilliseconds)->toBe(1000)
        ->and($budgets['B-04']->maxMilliseconds)->toBe(300)
        ->and($budgets['B-05']->maxMilliseconds)->toBe(1000)
        ->and($budgets['B-06']->maxMilliseconds)->toBe(3000)
        ->and($budgets['B-07']->maxMilliseconds)->toBe(2000);

    expect(PerformanceBudget::structuralBudgets()['S-01'])->toBe(10)
        ->and(PerformanceBudget::structuralBudgets()['S-02'])->toBe(6)
        ->and(PerformanceBudget::structuralBudgets()['S-04'])->toBe(200);
});

it('covers a new module without anyone defining a new budget', function (): void {
    // FR-014. Budgets attach to the class of interaction, not to the module, so
    // a module added later inherits them. If a per-module budget ever appears,
    // this is the assumption it breaks.
    foreach (PerformanceBudget::timeBudgets() as $budget) {
        expect($budget)->not->toHaveProperty('module');
    }
});
