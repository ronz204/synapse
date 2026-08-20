<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 *
 * Read-only, so there is no create/update/delete: the history is written by
 * the Accreditation context in response to an equivalency, never from this
 * screen. Exports are likewise absent — RC-02b asks for the history to be
 * consultable, not extractable, and adding them would mean permissions no
 * requirement calls for.
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
}
