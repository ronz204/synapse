<?php

declare(strict_types=1);

use App\Enums\PlanClassification;
use Src\Curriculum\StudyPlan\Domain\Entities\CourseLink;
use Src\Curriculum\StudyPlan\Domain\Entities\Level;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCourseNotLinkedToPlanException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteCoursesMustDifferException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\PrerequisiteRequiredCourseMustBeInEarlierLevelException;
use Src\Curriculum\StudyPlan\Domain\Exceptions\TerminalPlanRequiresClosingDateException;

it('rejects a Terminal plan with no enrollment closing date', function (): void {
    StudyPlan::create(
        programId: 1,
        name: 'Plan 2020',
        implementationYear: 2020,
        classification: PlanClassification::Terminal,
        enrollmentClosingDate: null,
    );
})->throws(TerminalPlanRequiresClosingDateException::class);

it('allows a Terminal plan that has an enrollment closing date', function (): void {
    $plan = StudyPlan::create(
        programId: 1,
        name: 'Plan 2020',
        implementationYear: 2020,
        classification: PlanClassification::Terminal,
        enrollmentClosingDate: new DateTimeImmutable('+1 year'),
    );

    expect($plan->classification())->toBe(PlanClassification::Terminal);
});

it('allows an Active plan with no enrollment closing date', function (): void {
    $plan = StudyPlan::create(
        programId: 1,
        name: 'Plan 2024',
        implementationYear: 2024,
        classification: PlanClassification::Active,
        enrollmentClosingDate: null,
    );

    expect($plan->enrollmentClosingDate())->toBeNull();
});

it('rejects reclassifying a plan to Terminal without supplying a closing date', function (): void {
    $plan = StudyPlan::create(
        programId: 1,
        name: 'Plan 2024',
        implementationYear: 2024,
        classification: PlanClassification::Active,
        enrollmentClosingDate: null,
    );

    $plan->reclassify(PlanClassification::Terminal, null);
})->throws(TerminalPlanRequiresClosingDateException::class);

it('rejects a prerequisite where both courses are the same', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
    ]);

    $plan->addPrerequisite(requiredCourseId: 10, dependentCourseId: 10);
})->throws(PrerequisiteCoursesMustDifferException::class);

it('rejects a prerequisite whose course is not linked to any level of this plan', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
    ]);

    // Course 99 was never linked to any level of this plan.
    $plan->addPrerequisite(requiredCourseId: 10, dependentCourseId: 99);
})->throws(PrerequisiteCourseNotLinkedToPlanException::class);

it('accepts a prerequisite whose courses are both linked to this plan', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
        Level::reconstitute(2, 2, [new CourseLink(courseId: 20, credits: 4)]),
    ]);

    $plan->addPrerequisite(requiredCourseId: 10, dependentCourseId: 20);

    expect($plan->prerequisites())->toHaveCount(1);
    expect($plan->prerequisites()[0]->requiredCourseId())->toBe(10);
    expect($plan->prerequisites()[0]->dependentCourseId())->toBe(20);
});

it('rejects a prerequisite whose required course is not in a strictly earlier level than the dependent course', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
        Level::reconstitute(2, 2, [new CourseLink(courseId: 20, credits: 4)]),
    ]);

    // 20 is in level 2, 10 is in level 1 — the required course (20) is not
    // earlier than the dependent course (10), so this must be rejected.
    $plan->addPrerequisite(requiredCourseId: 20, dependentCourseId: 10);
})->throws(PrerequisiteRequiredCourseMustBeInEarlierLevelException::class);

it('rejects a prerequisite between two courses in the same level', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [
            new CourseLink(courseId: 10, credits: 4),
            new CourseLink(courseId: 11, credits: 4),
        ]),
    ]);

    $plan->addPrerequisite(requiredCourseId: 10, dependentCourseId: 11);
})->throws(PrerequisiteRequiredCourseMustBeInEarlierLevelException::class);

it('rejects a mutual prerequisite between two courses that would otherwise form a 2-node cycle', function (): void {
    // Without the level-order rule this would let A require B and B
    // require A — a cycle that makes the plan impossible to complete.
    // Enforcing "required course's level < dependent course's level"
    // rules this out structurally: whichever edge is added second always
    // fails, because it would need its required course's level to be both
    // lower and higher than the other course's level.
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
        Level::reconstitute(2, 2, [new CourseLink(courseId: 20, credits: 4)]),
    ]);

    $plan->addPrerequisite(requiredCourseId: 10, dependentCourseId: 20);

    $plan->addPrerequisite(requiredCourseId: 20, dependentCourseId: 10);
})->throws(PrerequisiteRequiredCourseMustBeInEarlierLevelException::class);

it('never evaluates prerequisite scoping against a stale level set', function (): void {
    // Course 20 starts out linked, so this prerequisite would be valid...
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
        Level::reconstitute(2, 2, [new CourseLink(courseId: 20, credits: 4)]),
    ]);

    // ...but replaceStructure() swaps in a level set where course 20 is no
    // longer linked, and re-validates every prerequisite against the *new*
    // set — it must reject, not silently keep validating against the old one.
    $plan->replaceStructure(
        levels: [Level::create(number: 1, courseLinks: [new CourseLink(courseId: 10, credits: 4)])],
        prerequisitePairs: [['required' => 10, 'dependent' => 20]],
    );
})->throws(PrerequisiteCourseNotLinkedToPlanException::class);

it('replaces the whole structure as one unit', function (): void {
    $plan = studyPlanWithLevels([
        Level::reconstitute(1, 1, [new CourseLink(courseId: 10, credits: 4)]),
    ]);

    $plan->replaceStructure(
        levels: [
            Level::create(number: 1, courseLinks: [new CourseLink(courseId: 10, credits: 3)]),
            Level::create(number: 2, courseLinks: [new CourseLink(courseId: 30, credits: 5)]),
        ],
        prerequisitePairs: [['required' => 10, 'dependent' => 30]],
    );

    expect($plan->levels())->toHaveCount(2);
    expect($plan->prerequisites())->toHaveCount(1);
});

/**
 * @param  array<int, Level>  $levels
 */
function studyPlanWithLevels(array $levels): StudyPlan
{
    return StudyPlan::reconstitute(
        id: 1,
        programId: 1,
        name: 'Plan under test',
        implementationYear: 2024,
        classification: PlanClassification::Active,
        enrollmentClosingDate: null,
        levels: $levels,
        prerequisites: [],
    );
}
