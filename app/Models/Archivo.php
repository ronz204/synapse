<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ArchivoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $archivable_type
 * @property int $archivable_id
 * @property string $tipo_documento
 * @property string $nombre_original
 * @property string $disco
 * @property string $ruta
 * @property string $mime_type
 * @property int $tamano_bytes
 * @property string $hash_sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uuid',
    'user_id',
    'archivable_type',
    'archivable_id',
    'tipo_documento',
    'nombre_original',
    'disco',
    'ruta',
    'mime_type',
    'tamano_bytes',
    'hash_sha256',
])]
class Archivo extends Model
{
    /** @use HasFactory<ArchivoFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function archivable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
