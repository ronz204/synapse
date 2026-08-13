<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Domain\Exceptions;

use DomainException;

/**
 * Mirrors the database's chk_courses_service_program check constraint: a
 * course not flagged as a shared "service" course must belong to a program.
 */
final class NonServiceCourseRequiresProgramException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self("Course [{$code}] is not a service course, so it must belong to a program.");
    }
}
