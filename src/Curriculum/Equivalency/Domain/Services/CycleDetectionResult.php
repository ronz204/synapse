<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Services;

use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;

/**
 * @property array<int, CourseNode> $chain ordered course chain that would close the cycle, empty when hasCycle is false
 */
final readonly class CycleDetectionResult
{
    /**
     * @param  array<int, CourseNode>  $chain
     */
    private function __construct(
        public bool $hasCycle,
        public array $chain,
    ) {}

    public static function noCycle(): self
    {
        return new self(false, []);
    }

    /**
     * @param  array<int, CourseNode>  $chain
     */
    public static function cycle(array $chain): self
    {
        return new self(true, $chain);
    }

    /**
     * Human-readable chain by course code, e.g. "A → B → C → A" — never an
     * internal id, per the acceptance criteria.
     */
    public function chainLabel(): string
    {
        return implode(' → ', array_map(fn (CourseNode $node): string => $node->code, $this->chain));
    }
}
