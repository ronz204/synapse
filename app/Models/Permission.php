<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $module
 * @property string $action
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['module', 'action', 'name', 'description'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * The name stays the single source of truth: `module` and `action` are the
     * same value split in two so the identity module can group and filter
     * without parsing on every read. Deriving them here keeps the columns
     * consistent no matter which code path writes the permission.
     */
    protected static function booted(): void
    {
        static::saving(function (self $permission): void {
            if (! $permission->isDirty('name') && $permission->module && $permission->action) {
                return;
            }

            $permission->module = Str::before($permission->name, '.');
            $permission->action = Str::contains($permission->name, '.')
                ? Str::after($permission->name, '.')
                : $permission->name;
        });
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('created_at');
    }

    /**
     * @return BelongsToMany<User, $this, PermissionUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(PermissionUser::class)
            ->withPivot('otorgado_por', 'created_at');
    }
}
