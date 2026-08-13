<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\Services\DirectionEdgeOrientation;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;

beforeEach(function (): void {
    $this->source = new CourseNode(1, 'OLD-101');
    $this->target = new CourseNode(2, 'NEW-101');
});

it('produces a single forward edge for OldToNew', function (): void {
    $edges = DirectionEdgeOrientation::edgesFor(EquivalencyDirection::OldToNew, $this->source, $this->target, 99);

    expect($edges)->toHaveCount(1);
    expect($edges[0]->from->id)->toBe(1);
    expect($edges[0]->to->id)->toBe(2);
    expect($edges[0]->equivalencyId)->toBe(99);
});

it('produces a single reversed edge for NewToOld', function (): void {
    // The stored pair is still (source=1, target=2), but passing the target
    // course is what accredits the source course in this direction — the
    // edge must point target -> source, not source -> target.
    $edges = DirectionEdgeOrientation::edgesFor(EquivalencyDirection::NewToOld, $this->source, $this->target, 99);

    expect($edges)->toHaveCount(1);
    expect($edges[0]->from->id)->toBe(2);
    expect($edges[0]->to->id)->toBe(1);
});

it('produces both edges for Bidirectional', function (): void {
    $edges = DirectionEdgeOrientation::edgesFor(EquivalencyDirection::Bidirectional, $this->source, $this->target, 99);

    expect($edges)->toHaveCount(2);
    expect([$edges[0]->from->id, $edges[0]->to->id])->toBe([1, 2]);
    expect([$edges[1]->from->id, $edges[1]->to->id])->toBe([2, 1]);
});
