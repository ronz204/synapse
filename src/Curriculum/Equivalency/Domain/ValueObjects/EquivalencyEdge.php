<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\ValueObjects;

/**
 * A single directed edge in the equivalency graph, oriented per the
 * accreditation-flow meaning of `direction` (see DirectionEdgeOrientation),
 * not a fixed source/target reading.
 */
final readonly class EquivalencyEdge
{
    public function __construct(
        public CourseNode $from,
        public CourseNode $to,
        public int $equivalencyId,
    ) {}
}
