<?php

declare(strict_types=1);

namespace App\Enums;

enum EquivalencyDirection: string
{
    case OldToNew = 'Anterior a nuevo';
    case NewToOld = 'Nuevo a anterior';
    case Bidirectional = 'Bidireccional';
}
