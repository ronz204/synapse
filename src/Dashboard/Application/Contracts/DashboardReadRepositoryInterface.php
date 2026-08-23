<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\Contracts;

use DateTimeImmutable;
use Src\Dashboard\Application\DTOs\DashboardSummaryData;

interface DashboardReadRepositoryInterface
{
    public function summary(
        DateTimeImmutable $today,
        DateTimeImmutable $alertThreshold,
        int $studentRowsLimit,
        int $activityLimit,
    ): DashboardSummaryData;
}
