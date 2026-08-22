<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\Exceptions;

use DomainException;

final class CourseAlreadySatisfiedException extends DomainException
{
    public static function forStudent(int $studentId, int $courseId): self
    {
        return new self('The student already has a passing outcome for this course.');
    }
}
