<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\DTOs;

final readonly class DashboardAlertsData
{
    public function __construct(
        public int $expiringResolutions,
        public int $closingTerminalPlans,
        public int $coursesWithoutValidResolution,
    ) {}
}
