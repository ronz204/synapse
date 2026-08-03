<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModalidadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property bool $requiere_resolucion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'modalidades')]
#[Fillable(['nombre', 'requiere_resolucion'])]
class Modalidad extends Model
{
    /** @use HasFactory<ModalidadFactory> */
    use HasFactory;

    /**
     * @return HasMany<Curso, $this>
     */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    /**
     * @return HasMany<ResolucionModalidad, $this>
     */
    public function resolucionesModalidad(): HasMany
    {
        return $this->hasMany(ResolucionModalidad::class);
    }
}
