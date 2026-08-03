<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanClasificacion;
use Database\Factories\PlanEstudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $carrera_id
 * @property string $nombre
 * @property int $anio_implementacion
 * @property PlanClasificacion $clasificacion
 * @property Carbon|null $fecha_cierre_matricula
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'planes_estudio')]
#[Fillable(['carrera_id', 'nombre', 'anio_implementacion', 'clasificacion', 'fecha_cierre_matricula'])]
class PlanEstudio extends Model
{
    /** @use HasFactory<PlanEstudioFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clasificacion' => PlanClasificacion::class,
            'fecha_cierre_matricula' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Carrera, $this>
     */
    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    /**
     * @return HasMany<Nivel, $this>
     */
    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class);
    }

    /**
     * @return HasMany<Requisito, $this>
     */
    public function requisitos(): HasMany
    {
        return $this->hasMany(Requisito::class);
    }

    /**
     * @return BelongsToMany<Estudiante, $this, EstudiantePlan>
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_plan')
            ->using(EstudiantePlan::class)
            ->withPivot('nivel_actual');
    }

    /**
     * @param  Builder<PlanEstudio>  $query
     * @return Builder<PlanEstudio>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('clasificacion', PlanClasificacion::Vigente);
    }

    /**
     * @param  Builder<PlanEstudio>  $query
     * @return Builder<PlanEstudio>
     */
    public function scopeTerminal(Builder $query): Builder
    {
        return $query->where('clasificacion', PlanClasificacion::Terminal);
    }
}
