<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;

/**
 * Carries only the conflicting equivalency's id — the Presentation layer
 * re-queries the full record via FindEquivalencyUseCase to display it,
 * rather than this exception serializing a whole entity.
 */
final class EquivalencyContradictionException extends DomainException
{
    private function __construct(
        string $message,
        private readonly int $existingEquivalencyId,
    ) {
        parent::__construct($message);
    }

    public static function forConflict(int $existingEquivalencyId): self
    {
        return new self(
            "An active equivalency already exists for this course pair and direction (id [{$existingEquivalencyId}]).",
            $existingEquivalencyId,
        );
    }

    public function existingEquivalencyId(): int
    {
        return $this->existingEquivalencyId;
    }
}
