<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Services;

use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;

/**
 * Deliberately trivial: the real weight is in the repository querying
 * exactly (source_course_id, target_course_id, direction) filtered to
 * Active status — the same triple the database's generated `active_key`
 * uniqueness backs — and in this staying its own named, tested piece rather
 * than an inline check buried in a use case.
 */
final class ContradictionDetectionService
{
    public function detect(?Equivalency $existingActiveForTriple): ContradictionDetectionResult
    {
        return $existingActiveForTriple === null
            ? ContradictionDetectionResult::none()
            : ContradictionDetectionResult::conflict($existingActiveForTriple);
    }
}
