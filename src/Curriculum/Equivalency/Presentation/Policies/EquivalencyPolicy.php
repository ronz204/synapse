<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Presentation\Policies;

use App\Models\User;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 *
 * No update()/delete(): an Equivalency is append-only — it is only ever
 * created or transitioned to Superseded via resolveContradiction(), never
 * edited or removed directly.
 */
class EquivalencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('equivalencies.view');
    }

    public function view(User $user, Equivalency $equivalency): bool
    {
        return $user->hasPermissionTo('equivalencies.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('equivalencies.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('equivalencies.create');
    }

    public function resolveContradiction(User $user, Equivalency $equivalency): bool
    {
        return $user->hasPermissionTo('equivalencies.resolve_contradiction');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('equivalencies.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('equivalencies.export_excel');
    }
}
