<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

/**
 * A level cannot be removed from a plan's structure while an active student
 * is currently sitting on it (student_plan.current_level). Nothing else in
 * the system reassigns or clears that value when a level disappears, so
 * silently allowing the removal would orphan those students: they'd stay
 * counted in student_plan, but invisible from every per-level "active
 * students" view, since nothing renders a badge for a level that no longer
 * exists — the reported symptom of the count "not adding up."
 */
final class LevelHasActiveStudentsException extends DomainException
{
    public static function forLevel(int $levelNumber, int $activeStudentCount): self
    {
        return new self(
            "Level {$levelNumber} cannot be removed: {$activeStudentCount} active ".
            'student(s) are currently assigned to it.'
        );
    }
}
