<?php

declare(strict_types=1);

namespace App\Enums;

enum LaboratoryType: string
{
    case ComputerLab = 'Laboratorio de cómputo';
    case ScienceLab = 'Laboratorio de ciencias';
    case LanguageLab = 'Laboratorio de idiomas';
}
