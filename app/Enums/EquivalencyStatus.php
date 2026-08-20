<?php

declare(strict_types=1);

namespace App\Enums;

enum EquivalencyStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
}
