<?php

declare(strict_types=1);

namespace Src\Curriculum\StudyPlan\Presentation\Policies;

use App\Models\User;
use Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 *
 * No delete(): a StudyPlan has no `active` column and this slice offers no
 * destructive/deactivation action for it — only create/edit (including a
 * classification transition) and viewing its structure.
 */
class StudyPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('study_plans.view');
    }

    public function view(User $user, StudyPlan $studyPlan): bool
    {
        return $user->hasPermissionTo('study_plans.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('study_plans.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('study_plans.create');
    }

    public function update(User $user, StudyPlan $studyPlan): bool
    {
        return $user->hasPermissionTo('study_plans.edit');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('study_plans.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('study_plans.export_excel');
    }
}
