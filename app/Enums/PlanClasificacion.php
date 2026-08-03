<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanClasificacion: string
{
    case Vigente = 'Vigente';
    case Terminal = 'Terminal';
}
