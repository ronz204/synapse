<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanClassification: string
{
    case Active = 'Vigente';
    case Terminal = 'Terminal';
}
