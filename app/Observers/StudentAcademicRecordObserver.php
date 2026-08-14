<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AcademicRecordStatus;
use App\Events\AcademicRecordMarkedPassed;
use App\Models\StudentAcademicRecord;

final class StudentAcademicRecordObserver
{
    /**
     * Fires only when the record just landed in Passed — either freshly
     * created that way, or updated into it — never on an unrelated resave
     * of a record that was already Passed (e.g. a later grade edit).
     */
    public function saved(StudentAcademicRecord $record): void
    {
        $justPassed = $record->wasRecentlyCreated
            ? $record->status === AcademicRecordStatus::Passed
            : $record->wasChanged('status') && $record->status === AcademicRecordStatus::Passed;

        if ($justPassed) {
            event(new AcademicRecordMarkedPassed($record->student_id, $record->course_id));
        }
    }
}
