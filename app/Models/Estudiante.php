<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstudianteFactory;
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
 * @property int|null $user_id
 * @property string $cedula
 * @property string $nombre
 * @property string $primer_apellido
 * @property string|null $segundo_apellido
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'cedula', 'nombre', 'primer_apellido', 'segundo_apellido'])]
class Estudiante extends Model
{
    /** @use HasFactory<EstudianteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<PlanEstudio, $this, EstudiantePlan>
     */
    public function planesEstudio(): BelongsToMany
    {
        return $this->belongsToMany(PlanEstudio::class, 'estudiante_plan')
            ->using(EstudiantePlan::class)
            ->withPivot('nivel_actual');
    }

    /**
     * @return HasMany<HistorialAcademico, $this>
     */
    public function historialAcademico(): HasMany
    {
        return $this->hasMany(HistorialAcademico::class);
    }

    /**
     * @param  Builder<Estudiante>  $query
     * @return Builder<Estudiante>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
