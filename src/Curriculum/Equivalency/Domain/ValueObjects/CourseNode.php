<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\ValueObjects;

/**
 * The minimal representation of a course the equivalency graph needs: an id
 * to key the graph traversal on, and a code to build a human-readable cycle
 * chain without the domain ever needing to know about the Course aggregate.
 */
final readonly class CourseNode
{
    public function __construct(
        public int $id,
        public string $code,
    ) {}
}
