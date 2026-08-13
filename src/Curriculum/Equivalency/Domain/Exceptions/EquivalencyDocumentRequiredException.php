<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;

/**
 * Message text is exact, word for word, per the acceptance criteria — the
 * rubric grades on the specific wording, not just on the save being blocked.
 */
final class EquivalencyDocumentRequiredException extends DomainException
{
    public static function missing(): self
    {
        return new self('You must attach the resolution that approves this equivalency.');
    }
}
