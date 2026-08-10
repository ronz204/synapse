<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Infrastructure\Persistence\Repositories\EloquentPermissionRepository;
use Src\IdentityAccess\Permission\Presentation\Policies\PermissionPolicy;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Infrastructure\Persistence\Repositories\EloquentRoleRepository;
use Src\IdentityAccess\Role\Presentation\Policies\RolePolicy;

/**
 * Wires the bounded contexts under src/ into the framework: each domain port is
 * bound to its Eloquent adapter, each entity to its policy, and every context
 * gets its own routes loaded.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /**
     * Domain ports mapped to the adapters that satisfy them.
     *
     * @var array<class-string, class-string>
     */
    private array $domainBindings = [
        RoleRepositoryInterface::class => EloquentRoleRepository::class,
        PermissionRepositoryInterface::class => EloquentPermissionRepository::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private array $domainPolicies = [
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
    ];

    public function register(): void
    {
        foreach ($this->domainBindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerSuperAdminBypass();
        $this->loadContextRoutes();
    }

    private function registerPolicies(): void
    {
        foreach ($this->domainPolicies as $entity => $policy) {
            Gate::policy($entity, $policy);
        }
    }

    /**
     * Superadmin passes every authorization check unconditionally. The seeders
     * already grant it every existing permission; this also covers permissions
     * added after the last seed run, with no re-sync needed.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(fn (Authenticatable $user): ?bool => method_exists($user, 'hasRole') && $user->hasRole('Superadmin')
            ? true
            : null);
    }

    private function loadContextRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (File::glob(base_path('src/*/*/Presentation/Routes/web.php')) as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }
}
