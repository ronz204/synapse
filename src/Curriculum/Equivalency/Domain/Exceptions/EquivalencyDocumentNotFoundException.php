<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;

/**
 * Distinct from EquivalencyNotFoundException: the equivalency itself exists,
 * but has no resolution document attached — e.g. a row inserted directly
 * (seed/import data) that bypassed RegisterEquivalencyUseCase, which is the
 * only normal write path that enforces EquivalencyDocumentRequiredException
 * at registration time.
 */
final class EquivalencyDocumentNotFoundException extends DomainException
{
    public static function forEquivalency(int $equivalencyId): self
    {
        return new self("Equivalency [{$equivalencyId}] has no resolution document attached.");
    }
}
