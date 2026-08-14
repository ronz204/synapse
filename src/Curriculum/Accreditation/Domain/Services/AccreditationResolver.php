<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Domain\Services;

use Src\Curriculum\Accreditation\Domain\ValueObjects\AccreditationGrant;
use Src\Curriculum\Accreditation\Domain\ValueObjects\StudentRecordSnapshot;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

/**
 * The core, pure operation this slice exists for: given a course a student
 * just passed and the graph of currently-Active equivalencies, determine
 * every course it now qualifies the student for.
 *
 * `EquivalencyEdge->from`/`->to` already carries the resolved
 * accreditation-flow direction (see DirectionEdgeOrientation in the
 * equivalency-graph-integrity slice) — this resolver never re-derives
 * direction, it only follows edges whose `from` matches the passed course.
 */
final class AccreditationResolver
{
    /**
     * @param  array<int, EquivalencyEdge>  $activeEdges  the full active graph, never a subset
     * @param  array<int, StudentRecordSnapshot>  $existingRecords  every record the student currently holds
     * @return array<int, AccreditationGrant>
     */
    public function resolve(int $passedCourseId, array $activeEdges, array $existingRecords): array
    {
        $grants = [];

        foreach ($activeEdges as $edge) {
            if ($edge->from->id !== $passedCourseId) {
                continue;
            }

            if ($this->alreadySatisfied($edge->to->id, $existingRecords)) {
                continue;
            }

            if ($this->alreadyGranted($edge->to->id, $edge->equivalencyId, $existingRecords)) {
                continue;
            }

            $grants[] = new AccreditationGrant($edge->to->id, $edge->equivalencyId);
        }

        return $grants;
    }

    /**
     * @param  array<int, StudentRecordSnapshot>  $existingRecords
     */
    private function alreadySatisfied(int $courseId, array $existingRecords): bool
    {
        foreach ($existingRecords as $record) {
            if ($record->courseId === $courseId && $record->satisfiesCourse()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, StudentRecordSnapshot>  $existingRecords
     */
    private function alreadyGranted(int $courseId, int $equivalencyId, array $existingRecords): bool
    {
        foreach ($existingRecords as $record) {
            if ($record->courseId === $courseId && $record->equivalencyId === $equivalencyId) {
                return true;
            }
        }

        return false;
    }
}
