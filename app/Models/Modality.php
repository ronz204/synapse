<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $requires_resolution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'modalities')]
#[Fillable(['name', 'requires_resolution'])]
class Modality extends Model
{
    /** @use HasFactory<ModalityFactory> */
    use HasFactory;

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @return HasMany<ModalityResolution, $this>
     */
    public function modalityResolutions(): HasMany
    {
        return $this->hasMany(ModalityResolution::class);
    }
}
