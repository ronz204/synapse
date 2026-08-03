<?php

declare(strict_types=1);

namespace App\Enums;

enum HistorialAcademicoEstado: string
{
    case Aprobado = 'Aprobado';
    case Reprobado = 'Reprobado';
    case AcreditadoPorEquiparacion = 'Acreditado por equiparación';
    case AcreditadoPorConvalidacion = 'Acreditado por convalidación';
    case RequisitoLevantado = 'Requisito levantado';
}
