@php
    // Route::has() alone only tells us the module is built into this branch
    // (see the comments below) — it says nothing about whether the signed-in
    // user is actually allowed to open it. Each flag here also runs the same
    // policy ability (`viewAny` against the entity class Gate::policy() maps
    // it to) that the destination component's own mount() enforces, so a
    // link only ever appears when the page behind it would not 403 the user
    // that's currently looking at this menu.
    $canViewRoles = Route::has('identityaccess.role.index')
        && Auth::user()->can('viewAny', \Src\IdentityAccess\Role\Domain\Entities\Role::class);
    $canViewPermissions = Route::has('identityaccess.permission.index')
        && Auth::user()->can('viewAny', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class);

    $canViewCourses = Route::has('curriculum.course.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\Course\Domain\Entities\Course::class);
    $canViewStudyPlans = Route::has('curriculum.study_plan.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan::class);
    $canViewEquivalencies = Route::has('curriculum.equivalency.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\Equivalency\Domain\Entities\Equivalency::class);
    $canViewAcademicHistory = Route::has('curriculum.academic_history.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory::class);
    $canViewModalities = Route::has('curriculum.modality.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\Modality\Domain\Entities\Modality::class);
    $canViewModalityAssignments = Route::has('curriculum.modality_assignment.index')
        && Auth::user()->can('viewAny', \Src\Curriculum\Modality\Domain\Entities\ModalityResolution::class);
@endphp
<aside class="sidebar" x-persist="sidebar" :class="{ 'mobile-open': mobileOpen, 'collapsed': collapsed }">
    <div class="logo-row">
        <div class="logo-wrap">
            <img src="{{ asset('images/logo-utn.avif') }}" alt="UTN" class="logo-img">
        </div>
        <div class="logo-text" data-labels>
            <span class="logo-title">{{ __('UTN System') }}</span>
            <span class="logo-sub">{{ __('Academic Management') }}</span>
        </div>
    </div>

    <nav class="nav-scroll">
        <div class="nav-group">
            <span class="nav-label" data-labels>{{ __('MAIN') }}</span>
            <a href="{{ route('dashboard') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                </svg>
                <span class="nav-text" data-labels>{{ __('Main Panel') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
        </div>

        {{-- Roles and Permissions live in the IdentityAccess module, which this branch
             does not carry yet. Route::has() keeps the group out of the markup until
             that module registers its routes, rather than throwing on render — and
             each link is additionally gated by the same viewAny policy ability its
             destination component's mount() enforces, so the group itself disappears
             for a user authorized for neither. --}}
        @if ($canViewRoles || $canViewPermissions)
        <div class="nav-group">
            <span class="nav-label" data-labels>{{ __('SYSTEM ADMINISTRATION') }}</span>

            @if ($canViewRoles)
            <a href="{{ route('identityaccess.role.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Roles') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewPermissions)
            <a href="{{ route('identityaccess.permission.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Permissions') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif
        </div>
        @endif

        {{-- Courses and Study Plans (RC-01) live in the Curriculum module, guarded the
             same way as the SYSTEM ADMINISTRATION group above: kept out of the markup
             until those routes are actually registered, and each link additionally
             gated by its destination's own viewAny policy ability. --}}
        @if ($canViewCourses || $canViewStudyPlans || $canViewEquivalencies || $canViewAcademicHistory || $canViewModalities || $canViewModalityAssignments)
        <div class="nav-group">
            <span class="nav-label" data-labels>{{ __('STUDY PLANS') }}</span>

            @if ($canViewCourses)
            <a href="{{ route('curriculum.course.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Courses') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewStudyPlans)
            <a href="{{ route('curriculum.study_plan.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 6.5c-1.6-1.3-3.8-2-6-2-.6 0-1 .4-1 1v11c0 .6.4 1 1 1 2.2 0 4.4.7 6 2 1.6-1.3 3.8-2 6-2 .6 0 1-.4 1-1v-11c0-.6-.4-1-1-1-2.2 0-4.4.7-6 2z"></path>
                    <line x1="12" y1="6.5" x2="12" y2="19.5"></line>
                </svg>
                <span class="nav-text" data-labels>{{ __('Study Plans') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewEquivalencies)
            <a href="{{ route('curriculum.equivalency.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 3a2 2 0 0 1 2 2v3.5"></path>
                    <path d="M19 12v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"></path>
                    <path d="M12 3v6h6"></path>
                    <path d="M8 13h5"></path>
                    <path d="M8 17h8"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Equivalencies') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewAcademicHistory)
            <a href="{{ route('curriculum.academic_history.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 7a3 3 0 1 0 6 0 3 3 0 0 0-6 0"></path>
                    <path d="M5 21v-1a5 5 0 0 1 5-5h1"></path>
                    <path d="M15 17h6"></path>
                    <path d="M18 14v6"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Academic History') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewModalities)
            <a href="{{ route('curriculum.modality.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                </svg>
                <span class="nav-text" data-labels>{{ __('Modalities') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif

            @if ($canViewModalityAssignments)
            <a href="{{ route('curriculum.modality_assignment.index') }}" wire:navigate wire:current="active" class="nav-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                <span class="nav-text" data-labels>{{ __('Modality Assignments') }}</span>
                <svg class="nav-chevron" data-labels width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6;">
                    <polyline points="9 6 15 12 9 18"></polyline>
                </svg>
            </a>
            @endif
        </div>
        @endif

    </nav>
</aside>
