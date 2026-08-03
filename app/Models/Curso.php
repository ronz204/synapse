<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoLaboratorio;
use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $carrera_id
 * @property int|null $modalidad_id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_servicio
 * @property bool $es_cuello_botella
 * @property bool $requiere_laboratorio
 * @property TipoLaboratorio|null $tipo_laboratorio
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'carrera_id',
    'modalidad_id',
    'codigo',
    'nombre',
    'es_servicio',
    'es_cuello_botella',
    'requiere_laboratorio',
    'tipo_laboratorio',
])]
class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_laboratorio' => TipoLaboratorio::class,
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
     * @return BelongsTo<Modalidad, $this>
     */
    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class);
    }

    /**
     * @return BelongsToMany<Nivel, $this, CursoNivel>
     */
    public function niveles(): BelongsToMany
    {
        return $this->belongsToMany(Nivel::class, 'curso_nivel')
            ->using(CursoNivel::class)
            ->withPivot('creditos');
    }

    /**
     * @return HasMany<ResolucionModalidad, $this>
     */
    public function resolucionesModalidad(): HasMany
    {
        return $this->hasMany(ResolucionModalidad::class);
    }

    /**
     * @return HasMany<Requisito, $this>
     */
    public function requisitosComoRequerido(): HasMany
    {
        return $this->hasMany(Requisito::class, 'curso_requerido_id');
    }

    /**
     * @return HasMany<Requisito, $this>
     */
    public function requisitosComoExigido(): HasMany
    {
        return $this->hasMany(Requisito::class, 'curso_exige_id');
    }

    /**
     * @return HasMany<Equiparacion, $this>
     */
    public function equiparacionesComoOrigen(): HasMany
    {
        return $this->hasMany(Equiparacion::class, 'curso_origen_id');
    }

    /**
     * @return HasMany<Equiparacion, $this>
     */
    public function equiparacionesComoDestino(): HasMany
    {
        return $this->hasMany(Equiparacion::class, 'curso_destino_id');
    }

    /**
     * @return HasMany<HistorialAcademico, $this>
     */
    public function historialAcademico(): HasMany
    {
        return $this->hasMany(HistorialAcademico::class);
    }

    /**
     * @param  Builder<Curso>  $query
     * @return Builder<Curso>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
