<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Services;

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

/**
 * DFS-based cycle detection, tracking the recursion stack so a chain of any
 * length is caught, not only short loops.
 *
 * The correct question is NOT "does the full graph, with the candidate's own
 * edges already added, contain a cycle?" — that would trivially reject every
 * Bidirectional equivalency between two otherwise-unconnected courses, since
 * its own two edges (A->B and B->A) already form a 2-node "cycle" with
 * itself, which is exactly what Bidirectional is supposed to mean, not a
 * violation. The correct question, asked once per edge the candidate
 * contributes: does a path already exist, in the pre-existing active graph
 * (never including the candidate's own sibling edges), from that edge's
 * destination back to its origin? If so, adding that edge closes a real
 * cycle, and the chain to report is that path plus the new edge.
 */
final class CycleDetectionService
{
    /**
     * @param  array<int, EquivalencyEdge>  $activeEdges  currently-active graph, loaded fresh by the caller for every check
     */
    public function detect(array $activeEdges, EquivalencyDirection $candidateDirection, CourseNode $candidateSource, CourseNode $candidateTarget): CycleDetectionResult
    {
        $candidateEdges = DirectionEdgeOrientation::edgesFor($candidateDirection, $candidateSource, $candidateTarget, equivalencyId: 0);

        foreach ($candidateEdges as $edge) {
            $path = $this->findPath($activeEdges, from: $edge->to, to: $edge->from, visited: [], stack: [$edge->to]);

            if ($path !== null) {
                return CycleDetectionResult::cycle([...$path, $edge->to]);
            }
        }

        return CycleDetectionResult::noCycle();
    }

    /**
     * Depth-first search with an explicit recursion stack, so the exact
     * course-by-course path can be reported when one is found.
     *
     * @param  array<int, EquivalencyEdge>  $edges
     * @param  array<int, int>  $visited  course ids already visited in this search
     * @param  array<int, CourseNode>  $stack  path taken so far, for the eventual chain
     * @return array<int, CourseNode>|null
     */
    private function findPath(array $edges, CourseNode $from, CourseNode $to, array $visited, array $stack): ?array
    {
        if ($from->id === $to->id) {
            return $stack;
        }

        $visited[] = $from->id;

        foreach ($edges as $edge) {
            if ($edge->from->id !== $from->id || in_array($edge->to->id, $visited, true)) {
                continue;
            }

            $found = $this->findPath($edges, $edge->to, $to, $visited, [...$stack, $edge->to]);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
