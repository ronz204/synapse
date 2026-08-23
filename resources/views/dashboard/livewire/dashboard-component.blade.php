<div class="space-y-5">
    <section aria-label="{{ __('Academic overview') }}" class="grid gap-4 lg:grid-cols-3">
        <a href="{{ route('curriculum.study_plan.index') }}" wire:navigate
            class="group min-h-48 rounded-lg border border-border-default bg-background-surface p-6 shadow-elevation-1 transition hover:border-border-brand hover:shadow-elevation-2 focus:outline-none focus-visible:ring-3 focus-visible:ring-border-focus">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-text-primary">{{ __('Study plans') }}</h2>
                    <p class="mt-4 text-5xl font-bold text-text-brand">{{ $summary->studyPlans }}</p>
                </div>
                <flux:icon.book-open-text class="size-11 text-text-brand" aria-hidden="true" />
            </div>
            <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-text-secondary">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-status-success"></span>{{ trans_choice(':count active|:count active', $summary->activeStudyPlans, ['count' => $summary->activeStudyPlans]) }}</span>
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-status-warning"></span>{{ trans_choice(':count terminal|:count terminal', $summary->terminalStudyPlans, ['count' => $summary->terminalStudyPlans]) }}</span>
            </div>
        </a>

        <a href="{{ route('curriculum.equivalency.index') }}" wire:navigate
            class="group min-h-48 rounded-lg border border-border-default bg-background-surface p-6 shadow-elevation-1 transition hover:border-border-brand hover:shadow-elevation-2 focus:outline-none focus-visible:ring-3 focus-visible:ring-border-focus">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-text-primary">{{ __('Equivalencies') }}</h2>
                    <p class="mt-4 text-5xl font-bold text-text-brand">{{ $summary->equivalencies }}</p>
                </div>
                <flux:icon.document-text class="size-11 text-text-brand" aria-hidden="true" />
            </div>
            <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-text-secondary">
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-status-success"></span>{{ trans_choice(':count active|:count active', $summary->activeEquivalencies, ['count' => $summary->activeEquivalencies]) }}</span>
                <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-status-warning"></span>{{ trans_choice(':count superseded|:count superseded', $summary->supersededEquivalencies, ['count' => $summary->supersededEquivalencies]) }}</span>
            </div>
        </a>

        <a href="{{ route('curriculum.academic_history.index') }}" wire:navigate
            class="group min-h-48 rounded-lg border border-border-default bg-background-surface p-6 shadow-elevation-1 transition hover:border-border-brand hover:shadow-elevation-2 focus:outline-none focus-visible:ring-3 focus-visible:ring-border-focus">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-text-primary">{{ __('Accreditations') }}</h2>
                    <p class="mt-4 text-5xl font-bold text-text-brand">{{ $summary->studentsWithAccreditations }}</p>
                </div>
                <flux:icon.building-library class="size-11 text-text-brand" aria-hidden="true" />
            </div>
            <p class="mt-6 text-sm font-semibold text-text-primary">{{ trans_choice(':count accredited course|:count accredited courses', $summary->accreditedCourses, ['count' => $summary->accreditedCourses]) }}</p>
        </a>
    </section>

    <section class="grid overflow-hidden rounded-lg border border-border-default bg-background-surface shadow-elevation-1 xl:grid-cols-[minmax(0,1.7fr)_minmax(20rem,1fr)]">
        <div class="min-w-0 p-5 lg:p-6 xl:border-r xl:border-border-default">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-base font-semibold text-text-primary">{{ __('Active students by plan and level') }}</h2>
                <a href="{{ route('curriculum.study_plan.index') }}" wire:navigate class="shrink-0 text-sm font-semibold text-text-brand hover:underline">{{ __('View plans') }}</a>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[42rem] border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-default text-xs uppercase text-text-tertiary">
                            <th class="px-3 py-3 font-semibold">{{ __('Plan') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Program') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Level') }}</th>
                            <th class="px-3 py-3 text-center font-semibold">{{ __('Active students') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @forelse ($summary->activeStudentsByLevel as $row)
                            <tr wire:key="plan-level-{{ $row->studyPlanId }}-{{ $row->level }}" class="text-text-secondary">
                                <td class="px-3 py-4 font-medium text-text-primary">{{ $row->studyPlan }}</td>
                                <td class="px-3 py-4">{{ $row->program }}</td>
                                <td class="px-3 py-4">{{ __('Level :level', ['level' => $row->level]) }}</td>
                                <td class="px-3 py-4 text-center"><span class="inline-flex min-w-10 justify-center rounded-md bg-background-brand-subtle px-3 py-1 font-bold text-text-brand">{{ $row->activeStudents }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-12 text-center text-text-tertiary">{{ __('No active students assigned to a plan.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border-t border-border-default p-5 lg:p-6 xl:border-t-0">
            <section aria-labelledby="attention-required-title">
                <h2 id="attention-required-title" class="text-base font-semibold text-text-primary">{{ __('Attention required') }}</h2>
                <div class="mt-4 divide-y divide-border-default">
                    <a href="{{ route('curriculum.modality_assignment.index') }}" wire:navigate class="flex min-h-14 items-center gap-3 py-3 text-sm text-text-primary hover:text-text-brand">
                        <flux:icon.clock class="size-5 shrink-0 text-status-warning" aria-hidden="true" />
                        <span class="flex-1">{{ trans_choice(':count resolution expires soon|:count resolutions expire soon', $summary->alerts->expiringResolutions, ['count' => $summary->alerts->expiringResolutions]) }}</span>
                        <flux:icon.chevron-right class="size-4 shrink-0" aria-hidden="true" />
                    </a>
                    <a href="{{ route('curriculum.study_plan.index') }}" wire:navigate class="flex min-h-14 items-center gap-3 py-3 text-sm text-text-primary hover:text-text-brand">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-status-warning" aria-hidden="true" />
                        <span class="flex-1">{{ trans_choice(':count terminal plan closes enrollment soon|:count terminal plans close enrollment soon', $summary->alerts->closingTerminalPlans, ['count' => $summary->alerts->closingTerminalPlans]) }}</span>
                        <flux:icon.chevron-right class="size-4 shrink-0" aria-hidden="true" />
                    </a>
                    <a href="{{ route('curriculum.modality_assignment.index') }}" wire:navigate class="flex min-h-14 items-center gap-3 py-3 text-sm text-text-primary hover:text-text-brand">
                        <flux:icon.exclamation-circle class="size-5 shrink-0 text-status-danger" aria-hidden="true" />
                        <span class="flex-1">{{ trans_choice(':count course has no current resolution|:count courses have no current resolution', $summary->alerts->coursesWithoutValidResolution, ['count' => $summary->alerts->coursesWithoutValidResolution]) }}</span>
                        <flux:icon.chevron-right class="size-4 shrink-0" aria-hidden="true" />
                    </a>
                </div>
            </section>

            <section aria-labelledby="recent-activity-title" class="mt-6 border-t border-border-default pt-6">
                <h2 id="recent-activity-title" class="text-base font-semibold text-text-primary">{{ __('Recent activity') }}</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($summary->recentActivity as $activity)
                        <div class="flex items-start gap-3 text-sm">
                            <flux:icon.document-text class="mt-0.5 size-5 shrink-0 text-text-brand" aria-hidden="true" />
                            <p class="min-w-0 flex-1 text-text-secondary">
                                @if ($activity->type === 'equivalency')
                                    {{ __('Equivalency :subject registered', ['subject' => $activity->subject]) }}
                                @else
                                    {{ __('Study plan :subject updated', ['subject' => $activity->subject]) }}
                                @endif
                            </p>
                            <time datetime="{{ $activity->occurredAt->format(DATE_ATOM) }}" class="shrink-0 text-xs text-text-tertiary">{{ \Illuminate\Support\Carbon::instance($activity->occurredAt)->diffForHumans() }}</time>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-text-tertiary">{{ __('No recent activity.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</div>
