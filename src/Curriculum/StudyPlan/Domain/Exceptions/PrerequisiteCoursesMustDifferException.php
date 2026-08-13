<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

final class PrerequisiteCoursesMustDifferException extends DomainException
{
    public static function forCourse(int $courseId): self
    {
        return new self("A course [{$courseId}] cannot be a prerequisite of itself.");
    }
}
