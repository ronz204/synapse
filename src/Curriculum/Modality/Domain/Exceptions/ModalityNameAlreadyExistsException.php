<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

final class ModalityNameAlreadyExistsException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self("A modality named [{$name}] already exists.");
    }
}
