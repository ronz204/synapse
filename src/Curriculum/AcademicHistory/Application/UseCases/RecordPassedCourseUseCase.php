<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Application\UseCases;

use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicHistoryRepositoryInterface;
use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicRecordWriteRepositoryInterface;
use Src\Curriculum\AcademicHistory\Domain\Exceptions\CourseAlreadySatisfiedException;
use Src\Curriculum\AcademicHistory\Domain\Exceptions\StudentAcademicHistoryNotFoundException;
use Src\Curriculum\Course\Domain\Contracts\CourseRepositoryInterface;
use Src\Curriculum\Course\Domain\Exceptions\CourseNotFoundException;

/** Records the RC-02b input; Accreditation remains responsible for grants. */
final class RecordPassedCourseUseCase
{
    public function __construct(
        private readonly AcademicHistoryRepositoryInterface $histories,
        private readonly AcademicRecordWriteRepositoryInterface $records,
        private readonly CourseRepositoryInterface $courses,
    ) {}

    public function handle(int $studentId, int $courseId): void
    {
        if ($this->histories->findHistory($studentId) === null) {
            throw StudentAcademicHistoryNotFoundException::forStudent($studentId);
        }

        if ($this->courses->find($courseId) === null) {
            throw CourseNotFoundException::withId($courseId);
        }

        if ($this->records->hasPassingOutcome($studentId, $courseId)) {
            throw CourseAlreadySatisfiedException::forStudent($studentId, $courseId);
        }

        $this->records->recordPassed($studentId, $courseId);
    }
}
