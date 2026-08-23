<?php

declare(strict_types=1);

namespace Src\Dashboard\Infrastructure\Persistence\Repositories;

use App\Enums\AcademicRecordStatus;
use App\Enums\EquivalencyStatus;
use App\Enums\PlanClassification;
use App\Models\Course;
use App\Models\Equivalency;
use App\Models\ModalityResolution;
use App\Models\StudentAcademicRecord;
use App\Models\StudentPlan;
use App\Models\StudyPlan;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Src\Dashboard\Application\Contracts\DashboardReadRepositoryInterface;
use Src\Dashboard\Application\DTOs\ActiveStudentsByLevelData;
use Src\Dashboard\Application\DTOs\DashboardAlertsData;
use Src\Dashboard\Application\DTOs\DashboardSummaryData;
use Src\Dashboard\Application\DTOs\RecentActivityData;

final class EloquentDashboardReadRepository implements DashboardReadRepositoryInterface
{
    public function summary(
        DateTimeImmutable $today,
        DateTimeImmutable $alertThreshold,
        int $studentRowsLimit,
        int $activityLimit,
    ): DashboardSummaryData {
        $planCounts = $this->studyPlanCounts();
        $equivalencyCounts = $this->equivalencyCounts();
        $accreditationCounts = $this->accreditationCounts();

        return new DashboardSummaryData(
            studyPlans: $planCounts['total'],
            activeStudyPlans: $planCounts['active'],
            terminalStudyPlans: $planCounts['terminal'],
            equivalencies: $equivalencyCounts['total'],
            activeEquivalencies: $equivalencyCounts['active'],
            supersededEquivalencies: $equivalencyCounts['superseded'],
            studentsWithAccreditations: $accreditationCounts['students'],
            accreditedCourses: $accreditationCounts['courses'],
            activeStudentsByLevel: $this->activeStudentsByLevel($studentRowsLimit),
            alerts: $this->alerts($today, $alertThreshold),
            recentActivity: $this->recentActivity($activityLimit),
        );
    }

    /** @return array{total: int, active: int, terminal: int} */
    private function studyPlanCounts(): array
    {
        $row = StudyPlan::query()->selectRaw(
            'COUNT(*) AS total, SUM(CASE WHEN classification = ? THEN 1 ELSE 0 END) AS active_count, SUM(CASE WHEN classification = ? THEN 1 ELSE 0 END) AS terminal_count',
            [PlanClassification::Active->value, PlanClassification::Terminal->value],
        )->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'active' => (int) ($row?->getAttribute('active_count') ?? 0),
            'terminal' => (int) ($row?->getAttribute('terminal_count') ?? 0),
        ];
    }

    /** @return array{total: int, active: int, superseded: int} */
    private function equivalencyCounts(): array
    {
        $row = Equivalency::query()->selectRaw(
            'COUNT(*) AS total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS superseded_count',
            [EquivalencyStatus::Active->value, EquivalencyStatus::Superseded->value],
        )->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'active' => (int) ($row?->getAttribute('active_count') ?? 0),
            'superseded' => (int) ($row?->getAttribute('superseded_count') ?? 0),
        ];
    }

    /** @return array{students: int, courses: int} */
    private function accreditationCounts(): array
    {
        $query = StudentAcademicRecord::query()
            ->where('status', AcademicRecordStatus::AccreditedByEquivalency);

        return [
            'students' => (clone $query)->distinct()->count('student_id'),
            'courses' => $query->count(),
        ];
    }

    /** @return array<int, ActiveStudentsByLevelData> */
    private function activeStudentsByLevel(int $limit): array
    {
        return StudentPlan::query()
            ->join('students', 'students.id', '=', 'student_plan.student_id')
            ->join('study_plans', 'study_plans.id', '=', 'student_plan.study_plan_id')
            ->join('programs', 'programs.id', '=', 'study_plans.program_id')
            ->where('students.active', true)
            ->whereNotNull('student_plan.current_level')
            ->selectRaw('study_plans.id AS study_plan_id, study_plans.name AS study_plan, programs.name AS program, student_plan.current_level AS level, COUNT(*) AS active_students')
            ->groupBy('study_plans.id', 'study_plans.name', 'programs.name', 'student_plan.current_level')
            ->orderByDesc('active_students')
            ->orderBy('study_plans.name')
            ->orderBy('student_plan.current_level')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => new ActiveStudentsByLevelData(
                studyPlanId: (int) $row->getAttribute('study_plan_id'),
                studyPlan: (string) $row->getAttribute('study_plan'),
                program: (string) $row->getAttribute('program'),
                level: (int) $row->getAttribute('level'),
                activeStudents: (int) $row->getAttribute('active_students'),
            ))
            ->all();
    }

    private function alerts(DateTimeImmutable $today, DateTimeImmutable $threshold): DashboardAlertsData
    {
        $todayDate = $today->format('Y-m-d');
        $thresholdDate = $threshold->format('Y-m-d');

        $expiringResolutions = ModalityResolution::query()
            ->where('valid_from', '<=', $todayDate)
            ->whereBetween('valid_to', [$todayDate, $thresholdDate])
            ->count();

        $closingTerminalPlans = StudyPlan::query()
            ->terminal()
            ->whereBetween('enrollment_closing_date', [$todayDate, $thresholdDate])
            ->count();

        $coursesWithoutValidResolution = Course::query()
            ->active()
            ->whereHas('modality', fn (Builder $query) => $query->where('requires_resolution', true))
            ->whereDoesntHave('modalityResolutions', function (Builder $query) use ($todayDate): void {
                $query->whereColumn('modality_resolutions.modality_id', 'courses.modality_id')
                    ->where('valid_from', '<=', $todayDate)
                    ->where(function (Builder $query) use ($todayDate): void {
                        $query->whereNull('valid_to')->orWhere('valid_to', '>=', $todayDate);
                    });
            })
            ->count();

        return new DashboardAlertsData(
            expiringResolutions: $expiringResolutions,
            closingTerminalPlans: $closingTerminalPlans,
            coursesWithoutValidResolution: $coursesWithoutValidResolution,
        );
    }

    /** @return array<int, RecentActivityData> */
    private function recentActivity(int $limit): array
    {
        $equivalencies = Equivalency::query()
            ->latest('created_at')
            ->limit($limit)
            ->get(['resolution_number', 'created_at'])
            ->map(fn (Equivalency $equivalency) => new RecentActivityData(
                type: 'equivalency',
                subject: $equivalency->resolution_number,
                occurredAt: $equivalency->created_at->toDateTimeImmutable(),
            ));

        $studyPlans = StudyPlan::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['name', 'updated_at'])
            ->map(fn (StudyPlan $studyPlan) => new RecentActivityData(
                type: 'study_plan',
                subject: $studyPlan->name,
                occurredAt: $studyPlan->updated_at->toDateTimeImmutable(),
            ));

        /** @var Collection<int, RecentActivityData> $activity */
        $activity = $equivalencies->concat($studyPlans);

        return $activity
            ->sortByDesc(fn (RecentActivityData $item) => $item->occurredAt->getTimestamp())
            ->take($limit)
            ->values()
            ->all();
    }
}
