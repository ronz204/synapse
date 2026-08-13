<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\Services\CycleDetectionService;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

beforeEach(function (): void {
    $this->service = new CycleDetectionService;

    $this->a = new CourseNode(1, 'A');
    $this->b = new CourseNode(2, 'B');
    $this->c = new CourseNode(3, 'C');
    $this->d = new CourseNode(4, 'D');
    $this->e = new CourseNode(5, 'E');
});

it('finds no cycle against an empty graph', function (): void {
    $result = $this->service->detect([], EquivalencyDirection::OldToNew, $this->a, $this->b);

    expect($result->hasCycle)->toBeFalse();
});

it('finds no cycle when the candidate is unrelated to the existing graph', function (): void {
    $edges = [new EquivalencyEdge($this->a, $this->b, 1)];

    $result = $this->service->detect($edges, EquivalencyDirection::OldToNew, $this->c, $this->d);

    expect($result->hasCycle)->toBeFalse();
});

it('detects a 2-node cycle formed by two distinct active equivalencies', function (): void {
    // Active: A -> B. Candidate: B -> A (its own equivalency, different resolution).
    $edges = [new EquivalencyEdge($this->a, $this->b, 1)];

    $result = $this->service->detect($edges, EquivalencyDirection::OldToNew, $this->b, $this->a);

    expect($result->hasCycle)->toBeTrue();
    expect($result->chainLabel())->toBe('A → B → A');
});

it('does not reject a Bidirectional candidate between two otherwise-unconnected courses', function (): void {
    // A Bidirectional candidate's own two edges (A->B, B->A) trivially form a
    // 2-node loop with themselves — that is what Bidirectional *means*, not
    // a graph integrity violation, and must not be rejected.
    $result = $this->service->detect([], EquivalencyDirection::Bidirectional, $this->a, $this->b);

    expect($result->hasCycle)->toBeFalse();
});

it('detects a long cycle spanning five or more courses', function (): void {
    $edges = [
        new EquivalencyEdge($this->a, $this->b, 1),
        new EquivalencyEdge($this->b, $this->c, 2),
        new EquivalencyEdge($this->c, $this->d, 3),
        new EquivalencyEdge($this->d, $this->e, 4),
    ];

    $result = $this->service->detect($edges, EquivalencyDirection::OldToNew, $this->e, $this->a);

    expect($result->hasCycle)->toBeTrue();
    expect($result->chainLabel())->toBe('A → B → C → D → E → A');
});

it('detects a cycle that only exists through a Bidirectional edge\'s reverse direction', function (): void {
    // Active graph: A -> X -> B (two separate OldToNew equivalencies).
    $x = new CourseNode(6, 'X');
    $edges = [
        new EquivalencyEdge($this->a, $x, 1),
        new EquivalencyEdge($x, $this->b, 2),
    ];

    // Candidate: Bidirectional(A, B) contributes A->B (forward leg — no
    // existing path B..->A, so this leg alone is fine) and B->A (reverse
    // leg — A->X->B already exists, so this leg alone closes the cycle).
    $result = $this->service->detect($edges, EquivalencyDirection::Bidirectional, $this->a, $this->b);

    expect($result->hasCycle)->toBeTrue();
    expect($result->chainLabel())->toBe('A → X → B → A');
});

it('ignores an edge that is not part of the active graph passed in (superseded equivalencies excluded upstream)', function (): void {
    // Simulates the repository already having excluded a Superseded
    // equivalency's edge from activeGraph() — a chain that would have
    // closed a cycle through it must not be flagged.
    $edges = [
        new EquivalencyEdge($this->a, $this->b, 1),
        // C -> A intentionally omitted, as if that equivalency were Superseded.
    ];

    $result = $this->service->detect($edges, EquivalencyDirection::OldToNew, $this->b, $this->c);

    expect($result->hasCycle)->toBeFalse();
});
