<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;

/**
 * Mirrors the database's chk_equivalencies_distinct check constraint: a
 * course can never be equivalent to itself.
 */
final class EquivalencySourceAndTargetMustDifferException extends DomainException
{
    public static function forCourse(int $courseId): self
    {
        return new self("Course [{$courseId}] cannot be equivalent to itself; source and target must differ.");
    }
}
