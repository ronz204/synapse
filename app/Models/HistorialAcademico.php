<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HistorialAcademicoEstado;
use Database\Factories\HistorialAcademicoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $curso_id
 * @property int|null $periodo_academico_id
 * @property HistorialAcademicoEstado $estado
 * @property string|null $nota
 * @property int|null $equiparacion_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'historial_academico')]
#[Fillable(['estudiante_id', 'curso_id', 'periodo_academico_id', 'estado', 'nota'])]
class HistorialAcademico extends Model
{
    /** @use HasFactory<HistorialAcademicoFactory> */
    use HasFactory;

    /**
     * `equiparacion_id` queda fuera del fillable a propósito: solo lo
     * setea el flujo de acreditación por equiparación (RC-02b).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => HistorialAcademicoEstado::class,
        ];
    }

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    /**
     * @return BelongsTo<Equiparacion, $this>
     */
    public function equiparacion(): BelongsTo
    {
        return $this->belongsTo(Equiparacion::class);
    }
}
