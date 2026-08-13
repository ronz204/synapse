<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Services;

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

/**
 * Maps `direction` to graph edge orientation, separately from the entity and
 * from cycle detection on purpose — this is the resolved design decision
 * most likely to silently regress if it were buried inside a bigger class.
 *
 * The edge always follows the accreditation-flow meaning of `direction` —
 * the direction in which passing one course flows to accredit the other —
 * never a fixed source/target reading. `NewToOld` is the case that's easy to
 * get backwards: it produces an edge reversed relative to how the pair is
 * stored, because it's passing the *target* course that accredits the
 * *source* course in that case.
 */
final class DirectionEdgeOrientation
{
    /**
     * @return array<int, EquivalencyEdge>
     */
    public static function edgesFor(EquivalencyDirection $direction, CourseNode $source, CourseNode $target, int $equivalencyId): array
    {
        return match ($direction) {
            EquivalencyDirection::OldToNew => [new EquivalencyEdge($source, $target, $equivalencyId)],
            EquivalencyDirection::NewToOld => [new EquivalencyEdge($target, $source, $equivalencyId)],
            EquivalencyDirection::Bidirectional => [
                new EquivalencyEdge($source, $target, $equivalencyId),
                new EquivalencyEdge($target, $source, $equivalencyId),
            ],
        };
    }
}
