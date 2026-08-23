<?php

declare(strict_types=1);

namespace Src\Dashboard\Application\DTOs;

use DateTimeImmutable;

final readonly class RecentActivityData
{
    public function __construct(
        public string $type,
        public string $subject,
        public DateTimeImmutable $occurredAt,
    ) {}
}
