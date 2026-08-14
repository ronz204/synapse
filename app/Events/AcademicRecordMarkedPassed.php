<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Raised by StudentAcademicRecordObserver whenever a student's academic
 * record for a course transitions to Passed — by any code path, not just
 * one this event's consumers know about.
 */
final readonly class AcademicRecordMarkedPassed
{
    public function __construct(
        public int $studentId,
        public int $courseId,
    ) {}
}
