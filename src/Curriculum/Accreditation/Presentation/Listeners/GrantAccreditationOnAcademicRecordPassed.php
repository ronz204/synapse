<?php

declare(strict_types=1);

namespace Src\Curriculum\Accreditation\Presentation\Listeners;

use App\Events\AcademicRecordMarkedPassed;
use Src\Curriculum\Accreditation\Application\UseCases\GrantAccreditationsForPassedCourseUseCase;

final class GrantAccreditationOnAcademicRecordPassed
{
    public function __construct(
        private readonly GrantAccreditationsForPassedCourseUseCase $useCase,
    ) {}

    public function handle(AcademicRecordMarkedPassed $event): void
    {
        $this->useCase->handle($event->studentId, $event->courseId);
    }
}
