<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Domain\ValueObjects;

/**
 * A single accreditation to write: the course to accredit, and the
 * equivalency that authorizes it.
 */
final readonly class AccreditationGrant
{
    public function __construct(
        public int $courseId,
        public int $equivalencyId,
    ) {}
}
