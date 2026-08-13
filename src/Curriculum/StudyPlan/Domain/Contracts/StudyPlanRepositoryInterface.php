<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Contracts;

use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface StudyPlanRepositoryInterface
{
    /**
     * Hydrates the full aggregate — levels (with their course links) and
     * prerequisites included — since StudyPlan needs its own levels loaded
     * to validate prerequisite scoping against itself.
     */
    public function find(int $id): ?StudyPlan;

    /**
     * Full, unpaginated collection — backs client-side (Alpine) tables.
     * Does not need levels/prerequisites hydrated (the catalog table only
     * shows plan-level fields), so an adapter is free to load a lighter
     * projection here than find() does.
     *
     * @return array<int, StudyPlan>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, StudyPlan>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(StudyPlan $studyPlan): StudyPlan;

    /**
     * Count of currently active students per level, for this plan. Keyed by
     * level number (not level id) since that's how student_plan.current_level
     * is stored — a plain integer, not a foreign key to levels.id.
     *
     * @return array<int, int>
     */
    public function activeStudentCountsByLevel(int $planId): array;
}
