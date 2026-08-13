<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Services\ContradictionDetectionService;

it('finds no contradiction when nothing active exists for the triple', function (): void {
    $result = (new ContradictionDetectionService)->detect(null);

    expect($result->hasContradiction)->toBeFalse();
    expect($result->conflicting)->toBeNull();
});

it('finds a contradiction when an active equivalency already exists for the triple', function (): void {
    $existing = Equivalency::create(1, 2, EquivalencyDirection::OldToNew, 'R-1');

    $result = (new ContradictionDetectionService)->detect($existing);

    expect($result->hasContradiction)->toBeTrue();
    expect($result->conflicting)->toBe($existing);
});
