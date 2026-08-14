<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Infrastructure\Persistence\Repositories;

use App\Enums\AcademicRecordStatus;
use App\Models\StudentAcademicRecord as StudentAcademicRecordModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Curriculum\Accreditation\Domain\Contracts\AccreditationRepositoryInterface;
use Src\Curriculum\Accreditation\Domain\ValueObjects\StudentRecordSnapshot;

final class EloquentAccreditationRepository implements AccreditationRepositoryInterface
{
    public function studentIdsWithPassedRecordFor(int $courseId): array
    {
        return StudentAcademicRecordModel::query()
            ->where('course_id', $courseId)
            ->where('status', AcademicRecordStatus::Passed)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();
    }

    public function recordsFor(int $studentId): array
    {
        return StudentAcademicRecordModel::query()
            ->where('student_id', $studentId)
            ->get(['course_id', 'status', 'equivalency_id'])
            ->map(fn (StudentAcademicRecordModel $row) => new StudentRecordSnapshot(
                $row->course_id,
                $row->status,
                $row->equivalency_id,
            ))
            ->all();
    }

    public function grant(int $studentId, int $courseId, int $equivalencyId): void
    {
        try {
            // `equivalency_id` (and, by the same guard, `status`) is
            // deliberately not fillable — only this flow is meant to set
            // it — so forceCreate() is the one place allowed to bypass
            // that, same pattern as EloquentEquivalencyRepository::register().
            StudentAcademicRecordModel::query()->forceCreate([
                'student_id' => $studentId,
                'course_id' => $courseId,
                'status' => AcademicRecordStatus::AccreditedByEquivalency,
                'equivalency_id' => $equivalencyId,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent write already granted this exact combination —
            // same outcome, not a real error (see student_academic_records
            // _equivalency_unique backstop).
        }
    }
}
