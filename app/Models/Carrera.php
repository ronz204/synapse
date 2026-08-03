<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CarreraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nombre', 'activa'])]
class Carrera extends Model
{
    /** @use HasFactory<CarreraFactory> */
    use HasFactory;

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * @return HasMany<PlanEstudio, $this>
     */
    public function planesEstudio(): HasMany
    {
        return $this->hasMany(PlanEstudio::class);
    }

    /**
     * @param  Builder<Carrera>  $query
     * @return Builder<Carrera>
     */
    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('activa', true);
    }
}
