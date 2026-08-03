<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CursoNivelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $nivel_id
 * @property int $curso_id
 * @property int $creditos
 * @property Carbon|null $created_at
 */
#[Table(name: 'curso_nivel')]
#[Fillable(['nivel_id', 'curso_id', 'creditos'])]
class CursoNivel extends Pivot
{
    /** @use HasFactory<CursoNivelFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Nivel, $this>
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
