<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Services;

use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;

final readonly class ContradictionDetectionResult
{
    private function __construct(
        public bool $hasContradiction,
        public ?Equivalency $conflicting,
    ) {}

    public static function none(): self
    {
        return new self(false, null);
    }

    public static function conflict(Equivalency $conflicting): self
    {
        return new self(true, $conflicting);
    }
}
