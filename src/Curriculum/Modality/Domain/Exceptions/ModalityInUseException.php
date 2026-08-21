<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

/**
 * Mirrors the database's restrictOnDelete backstop on both courses.modality_id
 * and modality_resolutions.modality_id — this exception is what lets a user
 * see a specific rejection instead of a raw QueryException.
 */
final class ModalityInUseException extends DomainException
{
    private function __construct(
        string $message,
        private readonly int $modalityId,
    ) {
        parent::__construct($message);
    }

    public static function forId(int $modalityId): self
    {
        return new self("Modality with id [{$modalityId}] is still in use and cannot be deleted.", $modalityId);
    }

    public function modalityId(): int
    {
        return $this->modalityId;
    }
}
