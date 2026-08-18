<?php

declare(strict_types=1);

namespace App\Support\Performance;

use InvalidArgumentException;

/**
 * One performance budget: a ceiling on a class of interaction, expressed as a
 * percentile, traceable to the spec criterion that produced it.
 *
 * The numbers here mirror
 * specs/002-perceived-performance/contracts/performance-budgets.md, which is
 * the source of truth. If this class and that document ever disagree, the
 * document wins and this class is broken.
 *
 * Note what is NOT here: there is no per-module budget. Budgets attach to a
 * class of interaction, so a module added later is covered by the existing
 * budgets without anyone redefining anything (FR-014). Resist the pull to add
 * a "courses is allowed to be slower" exception — that is how a budget stops
 * meaning anything.
 */
final readonly class PerformanceBudget
{
    /**
     * @param  int  $percentile  Percentile the ceiling is evaluated at (50-100).
     * @param  int  $maxMilliseconds  The ceiling itself.
     * @param  string  $criterion  Spec criterion this budget descends from (e.g. 'SC-002').
     */
    private function __construct(
        public string $id,
        public ?InteractionClass $class,
        public string $metric,
        public int $percentile,
        public int $maxMilliseconds,
        public string $criterion,
    ) {
        if ($percentile < 50 || $percentile > 100) {
            throw new InvalidArgumentException("Percentile for budget [{$id}] must be between 50 and 100.");
        }
    }

    /**
     * Time budgets B-01 to B-07. A null class means the budget applies to every
     * class rather than one of them.
     *
     * @return array<string, self>
     */
    public static function timeBudgets(): array
    {
        $budgets = [
            new self('B-01', null, 'firstPaint', 100, 100, 'SC-001'),
            new self('B-02', InteractionClass::ModuleOpen, 'contentReady', 95, 500, 'SC-002'),
            new self('B-03', InteractionClass::ModuleOpen, 'contentReady', 99, 1000, 'SC-002'),
            new self('B-04', InteractionClass::InModule, 'contentReady', 95, 300, 'SC-003'),
            new self('B-05', InteractionClass::Write, 'contentReady', 95, 1000, 'SC-004'),
            new self('B-06', null, 'progressIndicator', 100, 3000, 'SC-005'),
            new self('B-07', InteractionClass::AppBoot, 'contentReady', 95, 2000, 'SC-006'),
        ];

        $keyed = [];

        foreach ($budgets as $budget) {
            $keyed[$budget->id] = $budget;
        }

        return $keyed;
    }

    /**
     * Structural budgets S-01 to S-06. These are not times: they are the
     * deterministic assertions the Pest layer makes, which do not vary with the
     * machine and therefore fail reproducibly.
     *
     * S-05 is absent on purpose and that absence is documented rather than
     * silent — see structuralNotes().
     *
     * @return array<string, int>
     */
    public static function structuralBudgets(): array
    {
        return [
            'S-01' => 10,   // SQL queries per module open
            'S-02' => 6,    // SQL queries per in-module interaction
            'S-03' => 0,    // resolved against the component's perPage at assert time
            'S-04' => 200,  // rows serialized by a client-mode list
            'S-06' => 0,    // framework imports under src/**/Domain
        ];
    }

    /**
     * Why S-05 has no ceiling, kept next to the budgets so nobody "fixes" the
     * gap later.
     *
     * Cycle detection loads the whole active equivalency graph, and it must
     * keep doing so. Capping the query count on the equivalency write path
     * would create pressure to cache that graph between requests, and a stale
     * graph lets a cycle through — the exact failure Principle II of the
     * constitution declares non-negotiable. The write path has a one-second
     * budget (B-05); that is the constraint it answers to, not a query count.
     */
    public static function structuralNotes(): string
    {
        return 'S-05 intentionally has no ceiling: capping queries on the equivalency write path '
            .'would invite caching the active graph, and a stale graph lets a cycle through.';
    }

    public static function byId(string $id): self
    {
        $budgets = self::timeBudgets();

        return $budgets[$id] ?? throw new InvalidArgumentException("Unknown performance budget [{$id}].");
    }

    /**
     * Budgets applying to a given class, including the class-agnostic ones.
     *
     * @return array<string, self>
     */
    public static function forClass(InteractionClass $class): array
    {
        return array_filter(
            self::timeBudgets(),
            static fn (self $budget): bool => $budget->class === null || $budget->class === $class,
        );
    }

    public function isMetBy(int $observedMilliseconds): bool
    {
        return $observedMilliseconds <= $this->maxMilliseconds;
    }

    /**
     * How far past the ceiling an observation landed, as a percentage. Returns
     * 0 when the budget is met. The report leads with this rather than the raw
     * time because "almost triple its ceiling" is more actionable than
     * "1.840 ms".
     */
    public function excessPercentage(int $observedMilliseconds): int
    {
        if ($this->isMetBy($observedMilliseconds)) {
            return 0;
        }

        return (int) round(($observedMilliseconds - $this->maxMilliseconds) / $this->maxMilliseconds * 100);
    }
}
