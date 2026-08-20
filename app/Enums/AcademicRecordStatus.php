<?php

declare(strict_types=1);

namespace App\Enums;

enum AcademicRecordStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case AccreditedByEquivalency = 'accredited_by_equivalency';
    case AccreditedByValidation = 'accredited_by_validation';
    case PrerequisiteWaived = 'prerequisite_waived';
}
