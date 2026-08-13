<?php

declare(strict_types=1);

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\Exceptions\EquivalencySourceAndTargetMustDifferException;

it('rejects a course being equivalent to itself', function (): void {
    Equivalency::create(sourceCourseId: 1, targetCourseId: 1, direction: EquivalencyDirection::OldToNew, resolutionNumber: 'R-1');
})->throws(EquivalencySourceAndTargetMustDifferException::class);

it('creates an Active equivalency by default', function (): void {
    $equivalency = Equivalency::create(1, 2, EquivalencyDirection::OldToNew, 'R-1');

    expect($equivalency->status())->toBe(EquivalencyStatus::Active);
    expect($equivalency->isActive())->toBeTrue();
    expect($equivalency->supersededById())->toBeNull();
});

it('transitions to Superseded, pointing at whichever equivalency prevailed, without losing its own data', function (): void {
    $equivalency = Equivalency::create(1, 2, EquivalencyDirection::OldToNew, 'R-1');

    $equivalency->supersedeBy(winnerId: 42);

    expect($equivalency->status())->toBe(EquivalencyStatus::Superseded);
    expect($equivalency->isActive())->toBeFalse();
    expect($equivalency->supersededById())->toBe(42);
    // Never deleted / never rewritten: the losing record's own facts survive.
    expect($equivalency->sourceCourseId())->toBe(1);
    expect($equivalency->targetCourseId())->toBe(2);
    expect($equivalency->resolutionNumber())->toBe('R-1');
});

it('reconstitutes a Superseded record exactly as stored', function (): void {
    $equivalency = Equivalency::reconstitute(
        id: 5,
        sourceCourseId: 1,
        targetCourseId: 2,
        direction: EquivalencyDirection::NewToOld,
        resolutionNumber: 'R-9',
        status: EquivalencyStatus::Superseded,
        supersededById: 6,
    );

    expect($equivalency->id())->toBe(5);
    expect($equivalency->direction())->toBe(EquivalencyDirection::NewToOld);
    expect($equivalency->status())->toBe(EquivalencyStatus::Superseded);
    expect($equivalency->supersededById())->toBe(6);
});
