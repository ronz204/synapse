<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

/**
 * A prerequisite's required and dependent course must both be linked (via
 * some level's course_level pivot) to the specific plan the prerequisite is
 * being registered against — not merely exist somewhere in the system.
 */
final class PrerequisiteCourseNotLinkedToPlanException extends DomainException
{
    public static function forCourses(int $requiredCourseId, int $dependentCourseId): self
    {
        return new self(
            "Both course [{$requiredCourseId}] and course [{$dependentCourseId}] must be linked to a level ".
            'of this study plan before they can be used in a prerequisite.'
        );
    }
}
