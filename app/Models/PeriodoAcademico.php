<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PeriodoAcademicoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $anio
 * @property int $cuatrimestre
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'periodos_academicos')]
#[Fillable(['anio', 'cuatrimestre', 'fecha_inicio', 'fecha_fin'])]
class PeriodoAcademico extends Model
{
    /** @use HasFactory<PeriodoAcademicoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /**
     * @return HasMany<HistorialAcademico, $this>
     */
    public function historialAcademico(): HasMany
    {
        return $this->hasMany(HistorialAcademico::class);
    }
}
