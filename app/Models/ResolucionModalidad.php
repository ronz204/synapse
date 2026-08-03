<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ResolucionModalidadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property int $modalidad_id
 * @property string $numero_resolucion
 * @property string $organo_aprobador
 * @property Carbon $vigencia_inicio
 * @property Carbon|null $vigencia_fin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'resoluciones_modalidad')]
#[Fillable(['curso_id', 'modalidad_id', 'numero_resolucion', 'organo_aprobador', 'vigencia_inicio', 'vigencia_fin'])]
class ResolucionModalidad extends Model
{
    /** @use HasFactory<ResolucionModalidadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fin' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<Modalidad, $this>
     */
    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class);
    }

    /**
     * Resoluciones vigentes hoy: la fecha de inicio ya pasó y no tienen fecha
     * de fin, o la fecha de fin todavía no se cumple.
     *
     * @param  Builder<ResolucionModalidad>  $query
     * @return Builder<ResolucionModalidad>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('vigencia_inicio', '<=', now())
            ->where(function (Builder $query) {
                $query->whereNull('vigencia_fin')
                    ->orWhere('vigencia_fin', '>=', now());
            });
    }
}
