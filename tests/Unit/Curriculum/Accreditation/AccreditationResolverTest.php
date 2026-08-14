<?php

declare(strict_types=1);

use App\Enums\AcademicRecordStatus;
use Src\Curriculum\Accreditation\Domain\Services\AccreditationResolver;
use Src\Curriculum\Accreditation\Domain\ValueObjects\StudentRecordSnapshot;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;

function accreditationNode(int $id): CourseNode
{
    return new CourseNode($id, "C-{$id}");
}

it('grants nothing when there are no active edges', function (): void {
    $resolver = new AccreditationResolver;

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [], existingRecords: []);

    expect($grants)->toBe([]);
});

it('grants the target course when the passed course is the edge source', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: []);

    expect($grants)->toHaveCount(1);
    expect($grants[0]->courseId)->toBe(2);
    expect($grants[0]->equivalencyId)->toBe(10);
});

it('never accredits the reverse direction — passing the edge target is not the edge source', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);

    $grants = $resolver->resolve(passedCourseId: 2, activeEdges: [$edge], existingRecords: []);

    expect($grants)->toBe([]);
});

it('accredits both directions of a bidirectional pair, one passed course at a time', function (): void {
    $resolver = new AccreditationResolver;
    $edges = [
        new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10),
        new EquivalencyEdge(accreditationNode(2), accreditationNode(1), equivalencyId: 10),
    ];

    $fromOne = $resolver->resolve(passedCourseId: 1, activeEdges: $edges, existingRecords: []);
    $fromTwo = $resolver->resolve(passedCourseId: 2, activeEdges: $edges, existingRecords: []);

    expect($fromOne)->toHaveCount(1)->and($fromOne[0]->courseId)->toBe(2);
    expect($fromTwo)->toHaveCount(1)->and($fromTwo[0]->courseId)->toBe(1);
});

it('skips a target course the student already Passed directly', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::Passed, equivalencyId: null)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toBe([]);
});

it('skips a target course already accredited by equivalency', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::AccreditedByEquivalency, equivalencyId: 99)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toBe([]);
});

it('skips a target course already accredited by another kind of validation', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::AccreditedByValidation, equivalencyId: null)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toBe([]);
});

it('does not treat a waived prerequisite as already satisfying the course', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::PrerequisiteWaived, equivalencyId: null)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toHaveCount(1);
});

it('never grants a duplicate for the same course and equivalency combination', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::AccreditedByEquivalency, equivalencyId: 10)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toBe([]);
});

it('grants for every active equivalency sharing the same source course', function (): void {
    $resolver = new AccreditationResolver;
    $edges = [
        new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 10),
        new EquivalencyEdge(accreditationNode(1), accreditationNode(3), equivalencyId: 11),
    ];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: $edges, existingRecords: []);

    expect($grants)->toHaveCount(2);
    expect(array_map(fn ($grant) => $grant->courseId, $grants))->toBe([2, 3]);
});

it('does not grant a second equivalency for a target course already accredited by a different one — the course is already satisfied', function (): void {
    $resolver = new AccreditationResolver;
    $edge = new EquivalencyEdge(accreditationNode(1), accreditationNode(2), equivalencyId: 20);
    $existing = [new StudentRecordSnapshot(courseId: 2, status: AcademicRecordStatus::AccreditedByEquivalency, equivalencyId: 1)];

    $grants = $resolver->resolve(passedCourseId: 1, activeEdges: [$edge], existingRecords: $existing);

    expect($grants)->toBe([]);
});
