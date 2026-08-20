<?php

declare(strict_types=1);

namespace App\Enums;

enum EquivalencyDirection: string
{
    case OldToNew = 'old_to_new';
    case NewToOld = 'new_to_old';
    case Bidirectional = 'bidirectional';
}
