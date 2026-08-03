<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstudiantePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $estudiante_id
 * @property int $plan_estudio_id
 * @property int|null $nivel_actual
 * @property Carbon|null $created_at
 */
#[Table(name: 'estudiante_plan')]
#[Fillable(['estudiante_id', 'plan_estudio_id', 'nivel_actual'])]
class EstudiantePlan extends Pivot
{
    /** @use HasFactory<EstudiantePlanFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Estudiante, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * @return BelongsTo<PlanEstudio, $this>
     */
    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class);
    }
}
