<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

/**
 * Mirrors the database's chk_study_plans_terminal_date check constraint: a
 * plan classified Terminal must carry an enrollment closing date.
 */
final class TerminalPlanRequiresClosingDateException extends DomainException
{
    public static function forPlan(string $name): self
    {
        return new self("Study plan [{$name}] is classified as Terminal, so it must have an enrollment closing date.");
    }
}
