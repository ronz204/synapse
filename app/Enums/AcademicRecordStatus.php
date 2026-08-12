<?php

declare(strict_types=1);

namespace App\Enums;

enum AcademicRecordStatus: string
{
    case Passed = 'Aprobado';
    case Failed = 'Reprobado';
    case AccreditedByEquivalency = 'Acreditado por equiparación';
    case AccreditedByValidation = 'Acreditado por convalidación';
    case PrerequisiteWaived = 'Requisito levantado';
}
