<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Exceptions;

use DomainException;
use Src\Curriculum\Equivalency\Domain\Services\CycleDetectionResult;

final class CycleDetectedException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $chainLabel,
    ) {
        parent::__construct($message);
    }

    public static function forChain(CycleDetectionResult $result): self
    {
        $chainLabel = $result->chainLabel();

        return new self(
            "Adding this equivalency would close a cycle: {$chainLabel}.",
            $chainLabel,
        );
    }

    public function chainLabel(): string
    {
        return $this->chainLabel;
    }
}
