<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\DTOs;

final readonly class DashboardSummaryData
{
    /**
     * @param  array<int, ActiveStudentsByLevelData>  $activeStudentsByLevel
     * @param  array<int, RecentActivityData>  $recentActivity
     */
    public function __construct(
        public int $studyPlans,
        public int $activeStudyPlans,
        public int $terminalStudyPlans,
        public int $equivalencies,
        public int $activeEquivalencies,
        public int $supersededEquivalencies,
        public int $studentsWithAccreditations,
        public int $accreditedCourses,
        public array $activeStudentsByLevel,
        public DashboardAlertsData $alerts,
        public array $recentActivity,
    ) {}
}
