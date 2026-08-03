<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequisitoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plan_estudio_id
 * @property int $curso_requerido_id
 * @property int $curso_exige_id
 * @property Carbon|null $created_at
 */
#[Fillable(['plan_estudio_id', 'curso_requerido_id', 'curso_exige_id'])]
class Requisito extends Model
{
    /** @use HasFactory<RequisitoFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlanEstudio, $this>
     */
    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function cursoRequerido(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_requerido_id');
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function cursoExige(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_exige_id');
    }
}
