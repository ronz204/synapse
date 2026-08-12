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
     * The permission_user table has a created_at column but deliberately no
     * updated_at: a grant is never edited in place, it is granted or revoked.
     *
     * Overriding the getter is what actually enforces that, because neither of
     * the two obvious declarations survives a pivot reached through a relation:
     *
     * - `const UPDATED_AT = null` is bypassed, since AsPivot::getUpdatedAtColumn()
     *   delegates to the pivotParent (User) whenever one is set, and so resolves
     *   to User's own 'updated_at'.
     * - `public $timestamps = false` is overwritten, since AsPivot::fromAttributes()
     *   reassigns it from hasTimestampAttributes() — and the attach record does
     *   carry created_at, because withPivot() below declares that column.
     *
     * Without this, User::givePermissionTo() fails outright with
     * "Unknown column 'updated_at' in 'field list'".
     *
     * created_at is unaffected: BelongsToMany populates it in
     * formatAttachRecords(), and getCreatedAtColumn() resolves correctly.
     */
    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

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
