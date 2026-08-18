<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Performance\InteractionClass;
use App\Support\Performance\InteractionMeasurement;
use App\Support\Performance\PerformanceBudget;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * The performance harness (feature 002-perceived-performance, research D-05).
 *
 * Two layers, because neither alone answers the question:
 *
 *   deterministic  Dispatches real requests through the HTTP kernel in-process
 *                  and counts SQL plus server render time. No browser, no
 *                  server to start, reproducible — but blind to paint, which
 *                  is what the spec's budgets are actually about.
 *   browser        Drives Chromium via resources/js/perf-probe.js and measures
 *                  what a person perceives. Needs the app served and is noisier
 *                  by nature.
 *
 * Verdicts are per module and interaction (FR-015), compared against
 * specs/002-perceived-performance/baseline.json (SC-008), and what could not be
 * measured is always stated rather than omitted — a report that is silent about
 * its gaps reads as full coverage.
 */
class MeasurePerformanceCommand extends Command
{
    protected $signature = 'perf:measure
        {--layer=deterministic : deterministic (in-process, no browser) or browser (real Chromium)}
        {--repetitions=20 : Observations per interaction; percentiles need a set, not a sample}
        {--concurrency=1 : Simultaneous sessions, for the 30-user requirement of SC-007}
        {--baseline : Write the result as the baseline instead of comparing against it}
        {--url=http://localhost:8000 : Base URL, browser layer only}
        {--user= : Email of the account to measure as; defaults to the first user}';

    protected $description = 'Measure interaction performance against the budgets in contracts/performance-budgets.md';

    private const BASELINE_PATH = 'specs/002-perceived-performance/baseline.json';

    /**
     * Noise floor for the baseline comparison, both conditions required.
     *
     * Measured, not guessed: four consecutive 20-repetition runs of the
     * unchanged deterministic layer produced 34/37/38/61 ms for the same module
     * open, and 44/51/56/72 ms for another. Swings of that size on interactions
     * an order of magnitude under budget are the machine, not the code.
     *
     * Without a floor the report flags three different "regressions" every run
     * and a different three next time — and a report that cries wolf every run
     * is a report nobody reads. Anything past both thresholds is still reported
     * in full; this only refuses to call ±20 ms a regression.
     */
    private const NOISE_FLOOR_MS = 25;

    private const NOISE_FLOOR_RATIO = 0.20;

    /**
     * The nine authenticated navigation entries. Budgets attach to the class of
     * interaction, not to the module, so a module added here needs no new
     * budget (FR-014).
     *
     * @return array<int, array{key: string, path: string, route: string, hasList: bool}>
     */
    private function modules(): array
    {
        $candidates = [
            ['key' => 'dashboard', 'path' => '/dashboard', 'route' => 'dashboard', 'hasList' => false],
            ['key' => 'study-plans', 'path' => '/study-plans', 'route' => 'curriculum.study_plan.index', 'hasList' => true],
            ['key' => 'courses', 'path' => '/courses', 'route' => 'curriculum.course.index', 'hasList' => true],
            ['key' => 'equivalencies', 'path' => '/equivalencies', 'route' => 'curriculum.equivalency.index', 'hasList' => true],
            ['key' => 'modalities', 'path' => '/modalities', 'route' => 'curriculum.modality.index', 'hasList' => true],
            ['key' => 'modality-assignments', 'path' => '/modality-assignments', 'route' => 'curriculum.modality_assignment.index', 'hasList' => true],
            ['key' => 'roles', 'path' => '/roles', 'route' => 'identityaccess.role.index', 'hasList' => true],
            ['key' => 'permissions', 'path' => '/permissions', 'route' => 'identityaccess.permission.index', 'hasList' => true],
            ['key' => 'settings', 'path' => '/settings/profile', 'route' => 'profile.edit', 'hasList' => false],
        ];

        return array_values(array_filter(
            $candidates,
            static fn (array $module): bool => app('router')->has($module['route']),
        ));
    }

    /**
     * The interactions the report must cover, per
     * contracts/performance-budgets.md. Anything here that a run could not
     * measure lands in notMeasured with a reason.
     *
     * @return array<int, string>
     */
    private function mandatoryCoverage(): array
    {
        $required = [];

        foreach ($this->modules() as $module) {
            $required[] = "{$module['key']}/open";

            if (! $module['hasList']) {
                continue;
            }

            foreach (['sort:first-column', 'paginate:next', 'search:hit', 'search:miss'] as $interaction) {
                $required[] = "{$module['key']}/{$interaction}";
            }
        }

        foreach ([
            'study-plans/save:create',
            'study-plans/save:structure',
            'equivalencies/save:create',
            'equivalencies/save:reject:cycle',
            'equivalencies/save:reject:contradiction',
            'modality-assignments/save:reject:no-modality-resolution',
            'courses/export:pdf',
        ] as $write) {
            $required[] = $write;
        }

        return $required;
    }

    public function handle(): int
    {
        $layer = (string) $this->option('layer');
        $repetitions = max(1, (int) $this->option('repetitions'));

        if (! in_array($layer, [InteractionMeasurement::LAYER_DETERMINISTIC, InteractionMeasurement::LAYER_BROWSER], true)) {
            $this->error("Unknown layer [{$layer}]. Use 'deterministic' or 'browser'.");

            return self::FAILURE;
        }

        $concurrency = max(1, (int) $this->option('concurrency'));

        // Refused rather than silently ignored. The deterministic layer runs in
        // one PHP process and cannot produce concurrent sessions; accepting the
        // flag and reporting "concurrency: 30" would put a number in the report
        // that nothing behind it measured — worse than not supporting it.
        if ($concurrency > 1 && $layer === InteractionMeasurement::LAYER_DETERMINISTIC) {
            $this->error('--concurrency is only meaningful on the browser layer.');
            $this->line('  The deterministic layer is a single process; it cannot open concurrent sessions.');
            $this->line('  Run: php artisan perf:measure --layer=browser --concurrency='.$concurrency);

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('No user to measure as. Create one, or pass --user=email.');

            return self::FAILURE;
        }

        $this->line("Measuring — layer: {$layer} · repetitions: {$repetitions} · as: {$user->email}");

        [$observations, $notMeasured] = $layer === InteractionMeasurement::LAYER_BROWSER
            ? $this->runBrowserLayer($repetitions)
            : $this->runDeterministicLayer($user, $repetitions);

        if ($observations === []) {
            $this->error('No observations collected — nothing to report.');

            // The reason lives in notMeasured, and the first version of this
            // discarded it here — reporting the failure while throwing away the
            // diagnosis, which made the harness impossible to debug from its
            // own output.
            foreach ($notMeasured as $gap) {
                $this->line(sprintf('  %s/%s: %s', $gap['module'], $gap['interaction'], $gap['reason']));
            }

            return self::FAILURE;
        }

        $rows = $this->buildRows($observations, $layer);
        $coverageGaps = $this->coverageGaps($rows, $layer);
        $report = $this->buildReport(
            $layer,
            $repetitions,
            $rows,
            // Deduped: the probe reports a gap once per repetition, so a
            // 20-repetition run listed the same three tables twenty times and
            // buried the gaps that mattered.
            $this->dedupeGaps([...$notMeasured, ...$coverageGaps]),
        );

        if ($this->option('baseline')) {
            return $this->writeBaseline($report);
        }

        $this->writeReport($report);
        $this->renderConsole($report);

        return $report['verdict'] === 'pass' ? self::SUCCESS : self::FAILURE;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('user');

        return $email !== null
            ? User::query()->where('email', $email)->first()
            : User::query()->orderBy('id')->first();
    }

    /**
     * Dispatches each module's route through the HTTP kernel with the user
     * already authenticated, timing the whole render and counting the SQL it
     * takes. This is a real request — middleware, policies, Blade and all — just
     * without a socket.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
     */
    private function runDeterministicLayer(User $user, int $repetitions): array
    {
        $kernel = app(HttpKernel::class);
        $observations = [];
        $notMeasured = [];

        foreach ($this->modules() as $module) {
            $bar = $this->output->createProgressBar($repetitions);
            $this->line("  {$module['key']}");
            $bar->start();

            for ($i = 0; $i < $repetitions; $i++) {
                try {
                    $queries = 0;

                    DB::listen(function () use (&$queries): void {
                        $queries++;
                    });

                    // setUser() on the guard rather than a session login: the
                    // synthetic request carries no cookie, and the guard is
                    // resolved once per application lifetime, so this makes
                    // auth middleware and policies see the user for real.
                    Auth::shouldUse('web');
                    Auth::guard('web')->setUser($user);

                    $request = Request::create($module['path'], 'GET');
                    $request->setUserResolver(static fn () => $user);

                    $startedAt = microtime(true);
                    $response = $kernel->handle($request);
                    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

                    // Anything other than a rendered page is not a measurement.
                    // A 302 to /login would otherwise be recorded as a very
                    // fast module open, which is exactly the kind of number
                    // that makes a harness worse than useless.
                    if ($response->getStatusCode() !== 200) {
                        $notMeasured[] = [
                            'module' => $module['key'],
                            'interaction' => 'open',
                            'reason' => "HTTP {$response->getStatusCode()} — not a rendered page; check the measuring user's permissions",
                        ];
                        break;
                    }

                    $observations[] = [
                        'module' => $module['key'],
                        'interaction' => 'open',
                        'class' => InteractionClass::ModuleOpen->value,
                        'contentReadyMs' => $elapsedMs,
                        'firstPaintMs' => null,
                        'queryCount' => $queries,
                        'serializedRows' => null,
                    ];
                } catch (Throwable $e) {
                    $notMeasured[] = [
                        'module' => $module['key'],
                        'interaction' => 'open',
                        'reason' => $e->getMessage(),
                    ];
                    break;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        return [$observations, $notMeasured];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
     */
    private function runBrowserLayer(int $repetitions): array
    {
        $probe = base_path('resources/js/perf-probe.js');

        if (! File::exists($probe)) {
            return [[], [['module' => '*', 'interaction' => '*', 'reason' => 'perf-probe.js missing']]];
        }

        if (! File::isDirectory(base_path('node_modules/puppeteer'))) {
            return [[], [['module' => '*', 'interaction' => '*', 'reason' => 'puppeteer not installed — run `bun install`']]];
        }

        $configPath = storage_path('app/performance/probe-config.json');

        File::ensureDirectoryExists(dirname($configPath));
        File::put($configPath, json_encode([
            'baseUrl' => rtrim((string) $this->option('url'), '/'),
            'email' => $this->resolveUser()?->email,
            'password' => config('performance.measure_password'),
            'repetitions' => $repetitions,
            'debounceMs' => 300,
            'modules' => $this->modules(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $result = Process::path(base_path())
            ->timeout(60 * 30)
            ->run(['node', $probe, $configPath]);

        if (! $result->successful()) {
            return [[], [[
                'module' => '*',
                'interaction' => '*',
                'reason' => 'probe failed: '.trim($result->errorOutput()),
            ]]];
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded)) {
            return [[], [['module' => '*', 'interaction' => '*', 'reason' => 'probe returned unparseable output']]];
        }

        return [$decoded['observations'] ?? [], $decoded['notMeasured'] ?? []];
    }

    /**
     * Collapses raw observations into one row per module and interaction,
     * evaluated at the percentile its budget demands. A single observation never
     * decides anything.
     *
     * @param  array<int, array<string, mixed>>  $observations
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(array $observations, string $layer): array
    {
        $grouped = [];

        foreach ($observations as $observation) {
            $key = $observation['module'].'/'.$observation['interaction'];
            $grouped[$key][] = $observation;
        }

        $rows = [];

        foreach ($grouped as $key => $set) {
            $class = InteractionClass::from($set[0]['class']);
            $budget = $this->budgetFor($class);

            $times = array_map(static fn (array $o): int => (int) $o['contentReadyMs'], $set);
            $observed = InteractionMeasurement::percentileOf($times, $budget->percentile);

            [$module, $interaction] = explode('/', $key, 2);

            $rows[] = [
                'module' => $module,
                'interaction' => $interaction,
                'class' => $class->value,
                'budget' => $budget->id,
                'percentile' => $budget->percentile,
                'observedMs' => $observed,
                'maxMs' => $budget->maxMilliseconds,
                'verdict' => $budget->isMetBy($observed) ? 'pass' : 'fail',
                'excessPercent' => $budget->excessPercentage($observed),
                // Null, never zero: a zero would claim the layer looked and
                // found none, which is a different and false statement.
                'firstPaintMs' => $layer === InteractionMeasurement::LAYER_BROWSER
                    ? $this->medianOfNullable($set, 'firstPaintMs')
                    : null,
                'queryCount' => $layer === InteractionMeasurement::LAYER_DETERMINISTIC
                    ? $this->medianOfNullable($set, 'queryCount')
                    : null,
                'serializedRows' => $this->medianOfNullable($set, 'serializedRows'),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['verdict'] === 'pass', $a['module'], $a['interaction']]
                <=> [$b['verdict'] === 'pass', $b['module'], $b['interaction']];
        });

        return $rows;
    }

    /**
     * One entry per module and interaction, keeping the first reason seen.
     *
     * @param  array<int, array<string, string>>  $gaps
     * @return array<int, array<string, string>>
     */
    private function dedupeGaps(array $gaps): array
    {
        $unique = [];

        foreach ($gaps as $gap) {
            $unique[$gap['module'].'/'.$gap['interaction']] ??= $gap;
        }

        return array_values($unique);
    }

    private function budgetFor(InteractionClass $class): PerformanceBudget
    {
        return match ($class) {
            InteractionClass::AppBoot => PerformanceBudget::byId('B-07'),
            InteractionClass::ModuleOpen => PerformanceBudget::byId('B-02'),
            InteractionClass::InModule => PerformanceBudget::byId('B-04'),
            InteractionClass::Write => PerformanceBudget::byId('B-05'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $set
     */
    private function medianOfNullable(array $set, string $field): ?int
    {
        $values = array_values(array_filter(
            array_map(static fn (array $o): mixed => $o[$field] ?? null, $set),
            static fn (mixed $v): bool => $v !== null,
        ));

        if ($values === []) {
            return null;
        }

        sort($values);

        return (int) $values[intdiv(count($values), 2)];
    }

    /**
     * Everything the mandatory coverage asks for that this run did not produce.
     *
     * The deterministic layer physically cannot drive a click or a form, so its
     * gaps are labelled as such rather than counted as failures — otherwise the
     * layer would always report fail and the signal would be worthless. A
     * browser run has no such excuse.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, string>>
     */
    private function coverageGaps(array $rows, string $layer): array
    {
        $measured = array_map(
            static fn (array $row): string => $row['module'].'/'.$row['interaction'],
            $rows,
        );

        $reason = $layer === InteractionMeasurement::LAYER_DETERMINISTIC
            ? 'not observable in the deterministic layer — run --layer=browser'
            : 'not measured by this run';

        $gaps = [];

        foreach ($this->mandatoryCoverage() as $required) {
            if (in_array($required, $measured, true)) {
                continue;
            }

            [$module, $interaction] = explode('/', $required, 2);
            $gaps[] = ['module' => $module, 'interaction' => $interaction, 'reason' => $reason];
        }

        return $gaps;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, string>>  $notMeasured
     * @return array<string, mixed>
     */
    private function buildReport(string $layer, int $repetitions, array $rows, array $notMeasured): array
    {
        $anyFailed = array_filter($rows, static fn (array $row): bool => $row['verdict'] === 'fail') !== [];

        // A browser run is the one expected to cover everything, so a gap there
        // is a failure. A deterministic run's gaps are structural, not a defect.
        $coverageFails = $layer === InteractionMeasurement::LAYER_BROWSER && $notMeasured !== [];

        return [
            'takenAt' => now()->toIso8601String(),
            'layer' => $layer,
            'repetitions' => $repetitions,
            'concurrency' => (int) $this->option('concurrency'),
            'volume' => $this->observedVolume(),
            'verdict' => $anyFailed || $coverageFails ? 'fail' : 'pass',
            'rows' => $rows,
            'notMeasured' => $notMeasured,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function observedVolume(): array
    {
        return [
            'programs' => DB::table('programs')->count(),
            'studyPlans' => DB::table('study_plans')->count(),
            'courses' => DB::table('courses')->count(),
            'prerequisites' => DB::table('prerequisites')->count(),
            'equivalencies' => DB::table('equivalencies')->count(),
            'modalityResolutions' => DB::table('modality_resolutions')->count(),
            'students' => DB::table('students')->count(),
            'academicRecords' => DB::table('student_academic_records')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeBaseline(array $report): int
    {
        $path = base_path(self::BASELINE_PATH);

        if (File::exists($path)) {
            $this->error('A baseline already exists at '.self::BASELINE_PATH.'.');
            $this->line('  It is deliberately written once, before any optimisation: SC-008 compares');
            $this->line('  against the state before this work, and regenerating it would erase that.');
            $this->line('  Delete it by hand only if you genuinely mean to discard the comparison.');

            return self::FAILURE;
        }

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info('Baseline written to '.self::BASELINE_PATH);
        $this->renderConsole($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(array $report): void
    {
        $path = storage_path('app/performance/report-'.now()->format('Ymd-His').'.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->line('Report written to '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
    }

    /**
     * Failures first, with the excess as a percentage: "almost triple its
     * ceiling" is more actionable than "1.840 ms".
     *
     * @param  array<string, mixed>  $report
     */
    private function renderConsole(array $report): void
    {
        $this->newLine();
        $this->line(sprintf(
            'Performance measurement — %s | layer: %s | repetitions: %d | concurrency: %d',
            $report['takenAt'],
            $report['layer'],
            $report['repetitions'],
            $report['concurrency'],
        ));
        $this->line(sprintf(
            'Volume: %d courses · %d equivalencies · %d students',
            $report['volume']['courses'],
            $report['volume']['equivalencies'],
            $report['volume']['students'],
        ));

        $failed = array_values(array_filter($report['rows'], static fn (array $r): bool => $r['verdict'] === 'fail'));
        $passed = array_values(array_filter($report['rows'], static fn (array $r): bool => $r['verdict'] === 'pass'));

        $this->newLine();
        $this->line(sprintf('FAIL (%d)', count($failed)));

        foreach ($failed as $row) {
            $this->line(sprintf(
                '  %-22s %-20s %-12s p%-3d %6d ms   max %5d ms   +%d%%',
                $row['module'], $row['interaction'], $row['class'],
                $row['percentile'], $row['observedMs'], $row['maxMs'], $row['excessPercent'],
            ));
        }

        $this->newLine();
        $this->line(sprintf('PASS (%d)', count($passed)));

        foreach ($passed as $row) {
            $this->line(sprintf(
                '  %-22s %-20s %-12s p%-3d %6d ms   max %5d ms',
                $row['module'], $row['interaction'], $row['class'],
                $row['percentile'], $row['observedMs'], $row['maxMs'],
            ));
        }

        // Printed even when empty, on purpose: silence about what was skipped
        // reads as full coverage, and it is not.
        $this->newLine();
        $this->line(sprintf('NOT MEASURED (%d)', count($report['notMeasured'])));

        foreach ($report['notMeasured'] as $gap) {
            $this->line(sprintf('  %-22s %-28s %s', $gap['module'], $gap['interaction'], $gap['reason']));
        }

        $this->renderBaselineComparison($report);

        $this->newLine();
        $this->line('Verdict: '.strtoupper($report['verdict']));
    }

    /**
     * SC-008 is an acceptance criterion like any other, so it belongs in the
     * same output rather than in a separate step someone has to remember.
     *
     * @param  array<string, mixed>  $report
     */
    private function renderBaselineComparison(array $report): void
    {
        $path = base_path(self::BASELINE_PATH);

        $this->newLine();

        if (! File::exists($path)) {
            $this->line('Baseline: none recorded yet — run with --baseline before optimising anything.');

            return;
        }

        $baseline = json_decode(File::get($path), true);

        if (! is_array($baseline) || ! isset($baseline['rows'])) {
            $this->line('Baseline: unreadable, comparison skipped.');

            return;
        }

        if (($baseline['layer'] ?? null) !== $report['layer']) {
            $this->line(sprintf(
                'Baseline: recorded on the %s layer, this run is %s — not comparable.',
                $baseline['layer'] ?? 'unknown',
                $report['layer'],
            ));

            return;
        }

        $before = [];

        foreach ($baseline['rows'] as $row) {
            $before[$row['module'].'/'.$row['interaction']] = $row['observedMs'];
        }

        $improved = $withinNoise = 0;
        $regressed = [];

        foreach ($report['rows'] as $row) {
            $key = $row['module'].'/'.$row['interaction'];

            if (! isset($before[$key])) {
                continue;
            }

            $delta = $row['observedMs'] - $before[$key];

            match (true) {
                $delta < -self::NOISE_FLOOR_MS => $improved++,
                $this->isWithinNoise($delta, $before[$key]) => $withinNoise++,
                default => $regressed[] = sprintf(
                    '%s (%d ms → %d ms, +%d%%)',
                    $key, $before[$key], $row['observedMs'],
                    (int) round($delta / max(1, $before[$key]) * 100),
                ),
            };
        }

        $this->line(sprintf(
            'Baseline comparison: %d improved · %d within noise · %d regressed',
            $improved, $withinNoise, count($regressed),
        ));
        $this->line(sprintf(
            '  Noise floor: a change counts only past %d ms AND %d%%.',
            self::NOISE_FLOOR_MS,
            (int) (self::NOISE_FLOOR_RATIO * 100),
        ));

        foreach ($regressed as $detail) {
            $this->line('  REGRESSED  '.$detail);
        }

        if ($regressed !== []) {
            $this->line('  SC-008 not met: no interaction may get worse than its baseline.');
        }
    }

    /**
     * True when a difference is small enough to be attributable to the machine
     * rather than the code.
     *
     * Either threshold clearing it is enough, which is the same thing as saying
     * a regression must exceed BOTH to be reported. The absolute floor protects
     * fast interactions, where a few milliseconds of scheduler jitter is a large
     * percentage; the ratio protects slow ones, where 25 ms is genuinely nothing.
     */
    private function isWithinNoise(int $delta, int $baselineMs): bool
    {
        return abs($delta) <= self::NOISE_FLOOR_MS
            || abs($delta) <= (int) round($baselineMs * self::NOISE_FLOOR_RATIO);
    }
}
