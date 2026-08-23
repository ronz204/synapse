<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Infrastructure\Persistence\Repositories;

use App\Enums\AcademicRecordStatus;
use App\Models\StudentAcademicRecord as StudentAcademicRecordModel;
use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicRecordWriteRepositoryInterface;

final class EloquentAcademicRecordWriteRepository implements AcademicRecordWriteRepositoryInterface
{
    public function hasPassingOutcome(int $studentId, int $courseId): bool
    {
        return StudentAcademicRecordModel::query()
            ->where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->whereIn('status', [
                AcademicRecordStatus::Passed,
                AcademicRecordStatus::AccreditedByEquivalency,
                AcademicRecordStatus::AccreditedByValidation,
            ])
            ->exists();
    }

    public function recordPassed(int $studentId, int $courseId): void
    {
        // Eloquent dispatches the observer event consumed by Accreditation.
        StudentAcademicRecordModel::query()->create([
            'student_id' => $studentId,
            'course_id' => $courseId,
            'status' => AcademicRecordStatus::Passed,
        ]);
    }
}
