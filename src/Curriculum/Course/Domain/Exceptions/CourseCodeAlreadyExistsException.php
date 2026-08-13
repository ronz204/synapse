<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Domain\Exceptions;

use DomainException;

/**
 * Course codes are unique across the whole system, not scoped to a program
 * or plan — the equivalency and modality slices both key off this identifier.
 */
final class CourseCodeAlreadyExistsException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self("A course with code [{$code}] already exists.");
    }
}
