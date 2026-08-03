<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoLaboratorio: string
{
    case LaboratorioComputo = 'Laboratorio de cómputo';
    case LaboratorioCiencias = 'Laboratorio de ciencias';
    case LaboratorioIdiomas = 'Laboratorio de idiomas';
}
