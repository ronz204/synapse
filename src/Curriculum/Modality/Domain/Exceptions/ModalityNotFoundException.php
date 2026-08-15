<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

final class ModalityNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Modality with id [{$id}] was not found.");
    }
}
