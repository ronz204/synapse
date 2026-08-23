<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\UseCases;

use DateTimeImmutable;
use Src\Dashboard\Application\Contracts\DashboardReadRepositoryInterface;
use Src\Dashboard\Application\DTOs\DashboardSummaryData;

final class GetDashboardSummaryUseCase
{
    private const ALERT_WINDOW_DAYS = 30;

    private const STUDENT_ROWS_LIMIT = 10;

    private const ACTIVITY_LIMIT = 5;

    public function __construct(
        private readonly DashboardReadRepositoryInterface $repository,
    ) {}

    public function handle(?DateTimeImmutable $today = null): DashboardSummaryData
    {
        $today ??= new DateTimeImmutable('today');

        return $this->repository->summary(
            today: $today,
            alertThreshold: $today->modify('+'.self::ALERT_WINDOW_DAYS.' days'),
            studentRowsLimit: self::STUDENT_ROWS_LIMIT,
            activityLimit: self::ACTIVITY_LIMIT,
        );
    }
}
