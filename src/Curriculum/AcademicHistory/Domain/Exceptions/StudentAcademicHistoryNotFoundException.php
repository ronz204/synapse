<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\Exceptions;

use DomainException;

final class StudentAcademicHistoryNotFoundException extends DomainException
{
    public static function forStudent(int $studentId): self
    {
        return new self("Academic history for student with id [{$studentId}] was not found.");
    }
}
