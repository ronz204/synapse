<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquiparacionEstado;
use App\Enums\EquiparacionSentido;
use Database\Factories\EquiparacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_origen_id
 * @property int $curso_destino_id
 * @property EquiparacionSentido $sentido
 * @property string $numero_resolucion
 * @property EquiparacionEstado $estado
 * @property int|null $sustituida_por_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'equiparaciones')]
#[Fillable(['curso_origen_id', 'curso_destino_id', 'sentido', 'numero_resolucion'])]
class Equiparacion extends Model
{
    /** @use HasFactory<EquiparacionFactory> */
    use HasFactory;

    /**
     * `estado` y `sustituida_por_id` quedan fuera del fillable a propósito:
     * solo los muta el flujo de resolución de contradicción (RC-02), nunca
     * una asignación masiva de creación.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => EquiparacionSentido::class,
            'estado' => EquiparacionEstado::class,
        ];
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function cursoOrigen(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_origen_id');
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function cursoDestino(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_destino_id');
    }

    /**
     * @return BelongsTo<Equiparacion, $this>
     */
    public function sustituidaPor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sustituida_por_id');
    }

    /**
     * @return HasMany<Equiparacion, $this>
     */
    public function equiparacionesSustituidas(): HasMany
    {
        return $this->hasMany(self::class, 'sustituida_por_id');
    }

    /**
     * @param  Builder<Equiparacion>  $query
     * @return Builder<Equiparacion>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', EquiparacionEstado::Vigente);
    }

    /**
     * @param  Builder<Equiparacion>  $query
     * @return Builder<Equiparacion>
     */
    public function scopeSustituida(Builder $query): Builder
    {
        return $query->where('estado', EquiparacionEstado::Sustituida);
    }
}
