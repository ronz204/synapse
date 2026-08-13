<?php

declare(strict_types=1);

namespace Src\Curriculum\Course\Presentation\Policies;

use App\Models\User;
use Src\Curriculum\Course\Domain\Entities\Course;

/**
 * Registered via Gate::policy() in DomainServiceProvider::$domainPolicies.
 * Superadmin bypasses all of this through Gate::before.
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('courses.view');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('courses.view');
    }

    public function search(User $user): bool
    {
        return $user->hasPermissionTo('courses.search');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('courses.edit');
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->hasPermissionTo('courses.delete');
    }

    public function exportPdf(User $user): bool
    {
        return $user->hasPermissionTo('courses.export_pdf');
    }

    public function exportExcel(User $user): bool
    {
        return $user->hasPermissionTo('courses.export_excel');
    }
}
