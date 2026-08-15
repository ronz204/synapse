<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Policies;

use App\Models\User;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before. Gates the
 * course-modality assignment flow — separate from ModalityPolicy, which
 * only covers catalog maintenance (RC-03's own spec requires two distinct
 * authorizations).
 */
class ModalityResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('modality_resolutions.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('modality_resolutions.search');
    }

    /**
     * Covers the combined "assign a modality (with its resolution) to a
     * course" action — there's no separate .edit/.delete, a resolution is
     * never edited or deleted once filed.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('modality_resolutions.create');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('modality_resolutions.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('modality_resolutions.export_excel');
    }
}
