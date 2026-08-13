<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;

final class EquivalencyNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Equivalency with id [{$id}] was not found.");
    }
}
