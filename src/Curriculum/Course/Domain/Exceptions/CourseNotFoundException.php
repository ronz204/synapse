<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Domain\Exceptions;

use DomainException;

final class CourseNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Course with id [{$id}] was not found.");
    }
}
