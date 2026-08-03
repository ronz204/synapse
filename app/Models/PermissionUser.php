<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PermissionUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $permission_id
 * @property int|null $otorgado_por
 * @property Carbon|null $created_at
 */
#[Table(name: 'permission_user')]
#[Fillable(['user_id', 'permission_id', 'otorgado_por'])]
class PermissionUser extends Pivot
{
    /** @use HasFactory<PermissionUserFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgado_por');
    }
}
