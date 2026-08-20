<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Infrastructure\Persistence\Repositories;

use App\Models\Student as StudentModel;
use App\Models\StudentAcademicRecord as StudentAcademicRecordModel;
use Illuminate\Database\Eloquent\Builder;
use Src\Curriculum\AcademicHistory\Domain\Contracts\AcademicHistoryRepositoryInterface;
use Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory;
use Src\Curriculum\AcademicHistory\Domain\ValueObjects\AcademicRecordEntry;
use Src\Curriculum\AcademicHistory\Domain\ValueObjects\StudentSummary;

final class EloquentAcademicHistoryRepository implements AcademicHistoryRepositoryInterface
{
    /**
     * Explicit allow-list — $sortBy ultimately reaches raw SQL via orderBy().
     *
     * @var array<int, string>
     */
    private const SORTABLE_COLUMNS = ['national_id', 'first_last_name'];

    /**
     * @var array<int, string>
     */
    private const SEARCHABLE_COLUMNS = ['national_id', 'first_name', 'first_last_name', 'second_last_name'];

    public function paginateStudents(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        $query = StudentModel::query();

        if (filled($search)) {
            $query->where(function (Builder $builder) use ($search): void {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'first_last_name';
        $direction = $sortDir === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($column, $direction)->paginate(perPage: $perPage, page: $page);

        return [
            'items' => array_map($this->toStudentSummary(...), $paginator->items()),
            'total' => $paginator->total(),
        ];
    }

    public function findHistory(int $studentId): ?StudentAcademicHistory
    {
        $student = StudentModel::query()->find($studentId);

        if ($student === null) {
            return null;
        }

        /**
         * `course` and `equivalency` are eager-loaded together with the rows:
         * a history is read one whole table at a time, so resolving either
         * per row would be a guaranteed N+1.
         */
        $records = StudentAcademicRecordModel::query()
            ->with(['course:id,code,name', 'equivalency:id,resolution_number'])
            ->where('student_id', $studentId)
            ->get();

        $entries = $records
            ->sortBy(fn (StudentAcademicRecordModel $record): string => $record->course->code)
            ->values()
            ->map($this->toEntry(...))
            ->all();

        return StudentAcademicHistory::reconstitute($this->toStudentSummary($student), $entries);
    }

    private function toEntry(StudentAcademicRecordModel $record): AcademicRecordEntry
    {
        return new AcademicRecordEntry(
            // `course` always resolves: the FK is non-nullable and
            // restrictOnDelete, so a course a record points at cannot vanish.
            // `equivalency` is the opposite — nullable, and set only where an
            // equivalency produced the record.
            courseCode: $record->course->code,
            courseName: $record->course->name,
            status: $record->status,
            resolutionNumber: $record->equivalency?->resolution_number,
        );
    }

    private function toStudentSummary(StudentModel $student): StudentSummary
    {
        $names = [$student->first_name, $student->first_last_name, $student->second_last_name];

        return new StudentSummary(
            id: $student->id,
            nationalId: $student->national_id,
            fullName: implode(' ', array_filter($names, static fn (?string $part): bool => filled($part))),
            active: (bool) $student->active,
        );
    }
}
