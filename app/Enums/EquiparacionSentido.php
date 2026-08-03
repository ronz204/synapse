<?php

declare(strict_types=1);

namespace App\Enums;

enum EquiparacionSentido: string
{
    case AnteriorANuevo = 'Anterior a nuevo';
    case NuevoAAnterior = 'Nuevo a anterior';
    case Bidireccional = 'Bidireccional';
}
