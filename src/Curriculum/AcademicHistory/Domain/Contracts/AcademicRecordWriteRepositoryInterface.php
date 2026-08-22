<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Domain\Contracts;

/** Output port for manually recording passed courses. */
interface AcademicRecordWriteRepositoryInterface
{
    public function hasPassingOutcome(int $studentId, int $courseId): bool;

    public function recordPassed(int $studentId, int $courseId): void;
}
