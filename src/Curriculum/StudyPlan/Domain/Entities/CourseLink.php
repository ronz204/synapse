<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Entities;

/**
 * Value object for a course linked to a Level, carrying the credits value
 * specific to that link — credits are never a property of Course itself,
 * since the same course can be worth a different number of credits in
 * different levels/plans.
 */
final readonly class CourseLink
{
    public function __construct(
        public int $courseId,
        public int $credits,
    ) {}
}
