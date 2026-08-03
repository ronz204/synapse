<?php

declare(strict_types=1);

namespace App\Enums;

enum EquiparacionEstado: string
{
    case Vigente = 'Vigente';
    case Sustituida = 'Sustituida';
}
