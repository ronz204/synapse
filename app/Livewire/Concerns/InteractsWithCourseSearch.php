<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Course as CourseModel;

/**
 * Backs <x-ui.course-combobox> — a course picker searched server-side
 * instead of rendered from a full `Course::all()`. Before this existed,
 * every "pick a course" <select> in the app (Equivalency's source/target,
 * ModalityAssignment's course field, StudyPlan structure's level/
 * prerequisite pickers) loaded and hydrated every active course on every
 * render, whether or not the picker was even open — invisible at demo
 * scale (15 courses) but a real, linearly-growing cost as the catalog
 * approaches institution scale (see performance.md's volume test, which is
 * what surfaced this).
 *
 * Deliberately does not go through a repository/use case: this is a plain
 * read for a UI affordance (matches courses to what the user is typing),
 * not a domain operation — the existing courseOptions() methods this
 * replaces read Course::query() directly for the same reason.
 */
trait InteractsWithCourseSearch
{
    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    protected function searchActiveCourses(string $term, int $limit = 30): array
    {
        $query = CourseModel::query()->active()->orderBy('code');

        if ($term !== '') {
            $query->where(function ($inner) use ($term): void {
                $inner->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        return $query->limit($limit)->get(['id', 'code', 'name'])
            ->map(fn (CourseModel $course): array => ['id' => $course->id, 'code' => $course->code, 'name' => $course->name])
            ->all();
    }
}
