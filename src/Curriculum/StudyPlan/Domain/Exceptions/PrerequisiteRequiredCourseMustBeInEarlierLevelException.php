<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

/**
 * A prerequisite's required course must belong to a level with a strictly
 * lower number than the dependent course's level — a student must be able
 * to have already passed the required course before reaching the level
 * that needs it. This also structurally rules out mutual/cyclic
 * prerequisites: a cycle would need the level number to both strictly
 * increase and return to its starting value, which is impossible.
 */
final class PrerequisiteRequiredCourseMustBeInEarlierLevelException extends DomainException
{
    public static function forCourses(
        int $requiredCourseId,
        int $dependentCourseId,
        int $requiredLevelNumber,
        int $dependentLevelNumber,
    ): self {
        return new self(
            "Course [{$requiredCourseId}] (level {$requiredLevelNumber}) cannot be a prerequisite of course ".
            "[{$dependentCourseId}] (level {$dependentLevelNumber}): the required course must belong to a ".
            'strictly earlier level of the same plan.'
        );
    }
}
