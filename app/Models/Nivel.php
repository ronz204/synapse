<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NivelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plan_estudio_id
 * @property int $numero
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'niveles')]
#[Fillable(['plan_estudio_id', 'numero'])]
class Nivel extends Model
{
    /** @use HasFactory<NivelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PlanEstudio, $this>
     */
    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class);
    }

    /**
     * @return BelongsToMany<Curso, $this, CursoNivel>
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_nivel')
            ->using(CursoNivel::class)
            ->withPivot('creditos');
    }
}
