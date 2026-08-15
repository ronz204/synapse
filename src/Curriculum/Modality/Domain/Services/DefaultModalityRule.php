<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Services;

/**
 * The single source of truth for "a course with no modality specified
 * defaults to in-person" (RC-03). Resolves by name, not by an assumed
 * identifier — the caller (an infrastructure adapter) still has to turn
 * this name into an id via a lookup, since the pure domain layer has no
 * way to know a database-assigned id.
 */
final class DefaultModalityRule
{
    public static function name(): string
    {
        return 'Presencial';
    }
}
