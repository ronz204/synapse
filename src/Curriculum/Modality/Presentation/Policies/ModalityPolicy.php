<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Policies;

use App\Models\User;
use Src\Curriculum\Modality\Domain\Entities\Modality;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before. Gates catalog
 * maintenance only — assigning a modality to a course is a separate
 * authorization, see ModalityResolutionPolicy.
 */
class ModalityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('modalities.view');
    }

    public function view(User $user, Modality $modality): bool
    {
        return $user->hasPermissionTo('modalities.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('modalities.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('modalities.create');
    }

    public function update(User $user, Modality $modality): bool
    {
        return $user->hasPermissionTo('modalities.edit');
    }

    public function delete(User $user, Modality $modality): bool
    {
        return $user->hasPermissionTo('modalities.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('modalities.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('modalities.export_excel');
    }
}
