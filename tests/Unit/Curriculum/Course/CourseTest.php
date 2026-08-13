<?php

declare(strict_types=1);

use Src\Curriculum\Course\Domain\Entities\Course;
use Src\Curriculum\Course\Domain\Exceptions\CourseCodeAlreadyExistsException;
use Src\Curriculum\Course\Domain\Exceptions\NonServiceCourseRequiresProgramException;

it('rejects a non-service course with no program', function (): void {
    Course::create(code: 'INF-101', name: 'Programming I', programId: null, isService: false);
})->throws(NonServiceCourseRequiresProgramException::class);

it('allows a non-service course that has a program', function (): void {
    $course = Course::create(code: 'INF-101', name: 'Programming I', programId: 1, isService: false);

    expect($course->programId())->toBe(1);
});

it('allows a service course with no program', function (): void {
    $course = Course::create(code: 'ENG-101', name: 'English I', programId: null, isService: true);

    expect($course->programId())->toBeNull();
    expect($course->isService())->toBeTrue();
});

it('allows a service course that also has a program', function (): void {
    // The DB check constraint is `is_service = 1 OR program_id IS NOT NULL` —
    // an OR, not an XOR — so a service course keeping a program is valid too.
    $course = Course::create(code: 'ENG-102', name: 'English II', programId: 1, isService: true);

    expect($course->programId())->toBe(1);
});

it('re-validates service/program consistency when attributes are updated', function (): void {
    $course = Course::create(code: 'INF-102', name: 'Programming II', programId: 1, isService: false);

    $course->updateAttributes(
        isService: false,
        programId: null,
        isBottleneck: false,
        requiresLaboratory: false,
        laboratoryType: null,
    );
})->throws(NonServiceCourseRequiresProgramException::class);

it('rejects a code that is already taken', function (): void {
    Course::assertCodeIsAvailable(codeAlreadyTaken: true, code: 'INF-101');
})->throws(CourseCodeAlreadyExistsException::class);

it('accepts a code that is available', function (): void {
    expect(fn () => Course::assertCodeIsAvailable(codeAlreadyTaken: false, code: 'INF-101'))
        ->not->toThrow(Throwable::class);
});

it('deactivates and reactivates a course', function (): void {
    $course = Course::create(code: 'INF-103', name: 'Programming III', programId: 1);

    expect($course->isActive())->toBeTrue();

    $course->deactivate();
    expect($course->isActive())->toBeFalse();

    $course->activate();
    expect($course->isActive())->toBeTrue();
});
