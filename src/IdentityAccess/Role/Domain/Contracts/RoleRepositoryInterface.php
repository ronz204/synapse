<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Domain\Contracts;

use Src\IdentityAccess\Role\Domain\Entities\Role;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface RoleRepositoryInterface
{
    public function find(int $id): ?Role;

    /**
     * Full, unpaginated collection — backs client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser. Reserved for
     * datasets small enough to ship to the client in one response.
     *
     * $search stays optional and unused by the client-side table (Alpine
     * already filters in the browser); it exists for exports, which must
     * reproduce exactly what the user currently sees on screen rather
     * than dumping the whole catalog.
     *
     * @return array<int, Role>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Role>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(Role $role): Role;

    public function delete(int $id): void;
}
