<?php

declare(strict_types=1);

use App\Support\Performance\PerformanceBudget;
use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityAssignmentComponent;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityComponent;
use Src\Curriculum\StudyPlan\Presentation\Livewire\StudyPlanComponent;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;

/**
 * Layer 1 of the performance harness — the deterministic one.
 *
 * Query counts and payload sizes do not vary with the machine, so unlike wall
 * time they fail reproducibly and can be trusted as a verdict. This is the
 * layer that makes FR-013 real; the browser layer (php artisan perf:measure)
 * covers what the user actually perceives.
 *
 * Structural budgets asserted here: S-01, S-02, S-03, S-04. S-05 is absent on
 * purpose — see PerformanceBudget::structuralNotes().
 *
 * Each test seeds the target volume once and then loops every module, rather
 * than using a dataset: a Pest dataset would re-seed 24.000 academic records
 * per case and turn a 20-second suite into a five-minute one.
 */

/**
 * @return array<string, array{component: class-string, permissions: array<int, string>, listKey: string}>
 */
function measuredModules(): array
{
    return [
        'courses' => [
            'component' => CourseComponent::class,
            'permissions' => ['courses.view', 'courses.search'],
            'listKey' => 'rows',
        ],
        'equivalencies' => [
            'component' => EquivalencyComponent::class,
            'permissions' => ['equivalencies.view', 'equivalencies.search'],
            'listKey' => 'rows',
        ],
        'study-plans' => [
            'component' => StudyPlanComponent::class,
            'permissions' => ['study_plans.view', 'study_plans.search'],
            'listKey' => 'rows',
        ],
        'modalities' => [
            'component' => ModalityComponent::class,
            'permissions' => ['modalities.view', 'modalities.search'],
            'listKey' => 'rows',
        ],
        'modality-assignments' => [
            'component' => ModalityAssignmentComponent::class,
            'permissions' => ['modality_resolutions.view', 'modality_resolutions.search'],
            'listKey' => 'rows',
        ],
        'roles' => [
            'component' => RoleComponent::class,
            'permissions' => ['roles.view', 'roles.search'],
            'listKey' => 'rows',
        ],
        'permissions' => [
            'component' => PermissionComponent::class,
            'permissions' => ['permissions.view', 'permissions.search'],
            'listKey' => 'rows',
        ],
    ];
}

/**
 * Runs $callback with SQL logging on and returns [result, queryCount].
 *
 * @return array{0: mixed, 1: int}
 */
function countingQueries(Closure $callback): array
{
    $count = 0;

    DB::listen(function () use (&$count): void {
        $count++;
    });

    $result = $callback();

    return [$result, $count];
}

/**
 * Livewire's viewData() throws on a missing key rather than returning null, and
 * not every component passes every key (ModalityAssignmentComponent has no
 * tableMode, for instance). The harness must not blow up over a key a component
 * legitimately does not set.
 */
function viewDataOr(object $component, string $key, mixed $default = null): mixed
{
    try {
        return $component->viewData($key);
    } catch (Throwable) {
        return $default;
    }
}

/**
 * Number of rows the component handed to the view. In client mode this is the
 * whole catalog; in server mode it is one page. That difference is the entire
 * point of budgets S-03 and S-04.
 */
function serializedRowCount(mixed $listData): int
{
    if ($listData === null) {
        return 0;
    }

    if (is_array($listData)) {
        return count($listData);
    }

    if ($listData instanceof Countable) {
        return count($listData);
    }

    return 0;
}

it('opens every module within the query budget S-01', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $ceiling = PerformanceBudget::structuralBudgets()['S-01'];
    $overBudget = [];

    foreach (measuredModules() as $module => $config) {
        $user = userWithPermissions($config['permissions']);

        [, $queries] = countingQueries(
            fn () => Livewire::actingAs($user)->test($config['component'])->assertOk()
        );

        if ($queries > $ceiling) {
            $overBudget[$module] = $queries;
        }
    }

    expect($overBudget)->toBe(
        [],
        sprintf(
            "S-01 exceeded (ceiling %d queries per module open):\n%s",
            $ceiling,
            implode("\n", array_map(
                static fn (string $m, int $q): string => "  {$m}: {$q} queries",
                array_keys($overBudget),
                $overBudget,
            )),
        ),
    );
});

it('keeps in-module interactions within the query budget S-02', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $ceiling = PerformanceBudget::structuralBudgets()['S-02'];
    $overBudget = [];

    foreach (measuredModules() as $module => $config) {
        $user = userWithPermissions($config['permissions']);

        $component = Livewire::actingAs($user)->test($config['component'])->assertOk();

        // Paginating is the cheapest interaction that always round-trips, in
        // both table modes — it isolates the cost of one re-render.
        [, $queries] = countingQueries(fn () => $component->call('nextPage'));

        if ($queries > $ceiling) {
            $overBudget[$module] = $queries;
        }
    }

    expect($overBudget)->toBe(
        [],
        sprintf(
            "S-02 exceeded (ceiling %d queries per in-module interaction):\n%s",
            $ceiling,
            implode("\n", array_map(
                static fn (string $m, int $q): string => "  {$m}: {$q} queries",
                array_keys($overBudget),
                $overBudget,
            )),
        ),
    );
});

it('never serializes more rows than a list is allowed to (S-03 and S-04)', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $clientCeiling = PerformanceBudget::structuralBudgets()['S-04'];
    $overBudget = [];

    foreach (measuredModules() as $module => $config) {
        $user = userWithPermissions($config['permissions']);

        $component = Livewire::actingAs($user)->test($config['component'])->assertOk();

        $mode = viewDataOr($component, 'tableMode', 'client');
        $rows = serializedRowCount(viewDataOr($component, $config['listKey']));

        // In server mode the paginator lives under the module's own key, not
        // under 'rows'; fall back to whichever the component actually passed.
        if ($mode === 'server' && $rows === 0) {
            foreach (['studyPlans', 'courses', 'equivalencies', 'modalities', 'roles', 'permissions'] as $candidate) {
                $rows = serializedRowCount(viewDataOr($component, $candidate));

                if ($rows > 0) {
                    break;
                }
            }
        }

        // S-03 for server mode: never more than one page. S-04 for client
        // mode: a catalog small enough that shipping it whole is the right
        // trade (the 200-row threshold from research decision D-01).
        $ceiling = $mode === 'server'
            ? $component->get('perPage')
            : $clientCeiling;

        if ($rows > $ceiling) {
            $overBudget[$module] = "{$rows} rows in {$mode} mode (ceiling {$ceiling})";
        }
    }

    expect($overBudget)->toBe(
        [],
        "S-03/S-04 exceeded:\n".implode("\n", array_map(
            static fn (string $m, string $detail): string => "  {$m}: {$detail}",
            array_keys($overBudget),
            $overBudget,
        )),
    );
});
