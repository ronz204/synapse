<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

final class ModalityNameAlreadyExistsException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $name,
    ) {
        parent::__construct($message);
    }

    public static function forName(string $name): self
    {
        return new self("A modality named [{$name}] already exists.", $name);
    }

    public function modalityName(): string
    {
        return $this->name;
    }
}
