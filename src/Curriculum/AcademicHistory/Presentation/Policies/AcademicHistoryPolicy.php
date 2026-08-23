<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 *
 * Create authorizes only the manual Passed input. Accreditation outcomes are
 * still generated exclusively by the Accreditation context and cannot be
 * entered or edited from this screen.
 */
class AcademicHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('academic_records.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('academic_records.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('academic_records.create');
    }
}
