<?php

declare(strict_types=1);

namespace App\Enums;

enum LaboratoryType: string
{
    case ComputerLab = 'computer_lab';
    case ScienceLab = 'science_lab';
    case LanguageLab = 'language_lab';
}
