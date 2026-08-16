<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Dependency-free role and permission checks for User, covering both
 * permissions inherited through roles and permissions granted directly.
 *
 * The roles() and permissions() relations deliberately live on the User model
 * rather than here: this project's pivots carry extra columns (created_at, and
 * granted_by on permission_user through the PermissionUser pivot model) that
 * a generic trait has no business redefining.
 *
 * @mixin Model
 *
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 */
trait HasRolesAndPermissions
{
    /**
     * Technical role with unconditional access to the whole system, seeded by
     * RoleSeeder and kept in sync with every permission by PermissionRoleSeeder.
     */
    public const SUPERADMIN_ROLE = 'Superadmin';

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    /**
     * Single source of truth for the unconditional-access role. Both this
     * trait's permission checks and DomainServiceProvider's Gate::before read
     * it, so the two authorization paths cannot drift apart — a Blade calling
     * hasPermissionTo() directly resolves the same way as one going through
     * the Gate via can().
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole(self::SUPERADMIN_ROLE);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function assignRole(string ...$roles): void
    {
        $ids = Role::query()->whereIn('name', $roles)->pluck('id');

        $this->roles()->syncWithoutDetaching($ids);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $ids = Role::query()->whereIn('name', $roles)->pluck('id');

        $this->roles()->sync($ids);
    }

    public function givePermissionTo(string ...$permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');

        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function revokePermissionTo(string ...$permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');

        $this->permissions()->detach($ids);
    }

    public function hasDirectPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        if ($this->hasDirectPermission($permission)) {
            return true;
        }

        return $this->roles->contains(
            fn (Role $role) => $role->permissions->contains('name', $permission)
        );
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
