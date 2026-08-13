<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Domain\Exceptions;

use DomainException;

final class StudyPlanNotFoundException extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Study plan with id [{$id}] was not found.");
    }
}
