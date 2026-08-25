@php
    $program = \App\Models\Program::query()->find($studyPlan->programId());
    $canEditPlan = Auth::user()->can('update', $studyPlan);

    // $structureLevels is kept sorted by number on the component side, so
    // the pager and the "current level" buttons below read 1, 2, 3... in
    // order regardless of the order levels were added or loaded in.
    $orderedLevels = $structureLevels;
    $activeLevel = collect($orderedLevels)->firstWhere('key', $activeLevelKey) ?? ($orderedLevels[0] ?? null);
    $activeIndex = $activeLevel ? collect($orderedLevels)->search(fn ($lvl) => $lvl['key'] === $activeLevel['key']) : null;
    $totalLevels = count($orderedLevels);

    // A prerequisite's required course must belong to a strictly earlier
    // level than the dependent course's (StudyPlan::addPrerequisite()'s
    // level-order invariant) — so the "add a required course" picker for
    // any course in $activeLevel only ever offers courses already linked
    // to a level numbered below $activeLevel['number'].
    $earlierLevelCourseIds = $activeLevel
        ? collect($orderedLevels)
            ->filter(fn ($level) => $level['number'] < $activeLevel['number'])
            ->flatMap(fn ($level) => collect($level['courses'])->pluck('course_id'))
            ->unique()
        : collect();

    // Same compact-with-ellipsis page set <x-ui.data-table> builds for its
    // own server-mode pager (first two, last two, and the active level's
    // immediate neighbours) — a plan with 20+ levels would otherwise render
    // 20+ pager buttons in a single unbroken row.
    if ($totalLevels > 1) {
        $currentPosition = $activeIndex + 1;
        $pageSet = $totalLevels <= 7
            ? range(1, $totalLevels)
            : collect([1, 2, $totalLevels - 1, $totalLevels, $currentPosition - 1, $currentPosition, $currentPosition + 1])
                ->filter(fn ($p) => $p >= 1 && $p <= $totalLevels)
                ->unique()
                ->sort()
                ->values()
                ->all();
    }
@endphp
<div>
    <div class="card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ $studyPlan->name() }}</span>
                <div style="margin-top: 4px;">
                    <span class="status-badge {{ $studyPlan->classification() === \App\Enums\PlanClassification::Terminal ? 'muted' : 'custom' }}">
                        {{ __($studyPlan->classification()->name) }}
                    </span>
                    <span style="margin-left: 8px; color: var(--textMuted);">{{ $program?->name }} · {{ $studyPlan->implementationYear() }}</span>
                    @if ($studyPlan->classification() === \App\Enums\PlanClassification::Terminal)
                    <span style="margin-left: 8px; color: var(--textMuted);">{{ __('Enrollment closes') }}: {{ $studyPlan->enrollmentClosingDate()?->format('Y-m-d') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-actions">
                <button type="button" class="btn btn-secondary" wire:click="backToCatalog">{{ __('Back to catalog') }}</button>
                @if ($canEditPlan)
                <button type="button" class="btn btn-orange" wire:click="saveStructure">{{ __('Save structure') }}</button>
                @endif
            </div>
        </div>
        @error('structurePrerequisites')
        <div class="card-controls">
            <flux:error name="structurePrerequisites" />
        </div>
        @enderror
    </div>

    @if ($canEditPlan)
    <div class="card" style="margin-top: 16px;">
        <div class="card-controls" style="justify-content: space-between;">
            <span class="text-sm" style="color: var(--color-text-tertiary);">
                {{ __('Levels are numbered automatically, in order.') }}
            </span>
            <button type="button" class="btn btn-primary" wire:click="addLevel">
                {{ __('Add level :number', ['number' => $this->nextLevelNumber()]) }}
            </button>
        </div>
    </div>
    @endif

    @if (! $activeLevel)
    <div class="card" style="margin-top: 16px;">
        <div class="empty-row">{{ __('No levels yet — add one above') }}</div>
    </div>
    @else
    <div class="card" style="margin-top: 16px;" wire:key="level-{{ $activeLevel['key'] }}">
        <div class="card-head">
            <span class="card-title">{{ __('Level') }} {{ $activeLevel['number'] }}</span>
            <div class="card-actions">
                <span class="status-badge system">{{ $activeStudentCounts[$activeLevel['number']] ?? 0 }} {{ __('active students') }}</span>
                @if ($canEditPlan)
                <button type="button" class="btn btn-secondary" wire:click="removeLevel('{{ $activeLevel['key'] }}')">{{ __('Remove level') }}</button>
                @endif
            </div>
        </div>

        @if ($totalLevels > 1 || $canEditPlan)
        {{--
            The level pager and the "add course" controls share this row,
            right below the level's title, instead of the pager living in
            its own card above and "add course" trailing after the table:
            paging between levels and adding a course to the level you just
            landed on are the same task, so keeping them visually together
            (and above the fold) saves a full card's worth of vertical
            space and reads as one control surface. `.card-controls`
            already wraps and space-betweens its children, so on narrow
            screens the pager and the add-course group simply stack instead
            of overlapping.
        --}}
        <div class="card-controls">
            @if ($totalLevels > 1)
            <div class="pagination">
                <button type="button" class="page-btn {{ $activeIndex <= 0 ? 'disabled' : '' }}" @disabled($activeIndex <= 0)
                    wire:click="goToLevel('{{ $orderedLevels[$activeIndex - 1]['key'] ?? '' }}')">{{ __('Previous') }}</button>

                @php $prevPosition = null; @endphp
                @foreach ($pageSet as $position)
                    @if (! is_null($prevPosition) && $position - $prevPosition > 1)
                    <span class="page-ellipsis">…</span>
                    @endif
                    @php $pagedLevel = $orderedLevels[$position - 1]; @endphp
                    <button type="button" class="page-btn {{ $pagedLevel['key'] === $activeLevel['key'] ? 'active' : '' }}" wire:click="goToLevel('{{ $pagedLevel['key'] }}')">
                        {{ __('Level') }} {{ $pagedLevel['number'] }}
                    </button>
                    @php $prevPosition = $position; @endphp
                @endforeach

                <button type="button" class="page-btn {{ $activeIndex >= $totalLevels - 1 ? 'disabled' : '' }}" @disabled($activeIndex >= $totalLevels - 1)
                    wire:click="goToLevel('{{ $orderedLevels[$activeIndex + 1]['key'] ?? '' }}')">{{ __('Next') }}</button>
            </div>
            @endif

            @if ($canEditPlan)
            <div class="control-group">
                <x-ui.course-combobox
                    id="newCourseForLevel{{ $activeLevel['key'] }}"
                    search-property="newCourseSearchByLevel.{{ $activeLevel['key'] }}"
                    select-action="selectCourseForLevel"
                    :action-args="[$activeLevel['key']]"
                    :options="$newCourseOptionsByLevel[$activeLevel['key']] ?? []"
                    style="min-width: 220px;"
                />
                <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model="newCourseSelection.{{ $activeLevel['key'] }}.credits" placeholder="{{ __('Credits') }}" style="width: 100px;">
                <button type="button" class="btn btn-primary" wire:click="addCourseToLevel('{{ $activeLevel['key'] }}')">{{ __('Add course') }}</button>
            </div>
            @endif
        </div>
        @endif

        <div class="table-scroll">
            <div class="table-inner" style="--table-cols: 1fr 2fr 0.8fr 1.6fr 0.8fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Name') }}</span>
                    <span>{{ __('Credits') }}</span>
                    <span>{{ __('Prerequisites') }}</span>
                    <span>{{ __('Actions') }}</span>
                </div>

                @forelse ($activeLevel['courses'] as $courseLink)
                @php
                    $courseInfo = $linkedCourseInfo[$courseLink['course_id']] ?? null;
                    $requiredCourseIds = collect($structurePrerequisites)->where('dependent_course_id', $courseLink['course_id'])->pluck('required_course_id')->values();
                    $requiredByCourseIds = collect($structurePrerequisites)->where('required_course_id', $courseLink['course_id'])->pluck('dependent_course_id')->values();
                    $prerequisiteCount = $requiredCourseIds->count() + $requiredByCourseIds->count();
                    $isExpanded = $expandedPrerequisiteCourseId === $courseLink['course_id'];
                @endphp
                <div class="data-row" role="row" wire:key="course-{{ $activeLevel['key'] }}-{{ $courseLink['course_id'] }}">
                    <span class="font-mono text-xs">{{ $courseInfo['code'] ?? $courseLink['course_id'] }}</span>
                    <span>{{ $courseInfo['name'] ?? '—' }}</span>
                    <span>{{ $courseLink['credits'] }}</span>
                    <span>
                        <button type="button" class="prereq-badge {{ $prerequisiteCount === 0 ? 'empty' : '' }} {{ $isExpanded ? 'open' : '' }}" wire:click="togglePrerequisitesFor({{ $courseLink['course_id'] }})">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                            </svg>
                            <span>{{ $prerequisiteCount > 0 ? $prerequisiteCount : __('None') }}</span>
                        </button>
                    </span>
                    <span>
                        @if ($canEditPlan)
                        <button type="button" class="action-icon delete" wire:click="removeCourseFromLevel('{{ $activeLevel['key'] }}', {{ $courseLink['course_id'] }})" title="{{ __('Remove') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        @endif
                    </span>
                </div>

                @if ($isExpanded)
                @php
                    $availableRequiredOptions = collect($linkedCourseInfo)
                        ->filter(fn ($course) => $earlierLevelCourseIds->contains($course['id']))
                        ->reject(fn ($course) => $requiredCourseIds->contains($course['id']))
                        ->values();
                @endphp
                <div class="prereq-panel" wire:key="prereq-panel-{{ $activeLevel['key'] }}-{{ $courseLink['course_id'] }}">
                    <div class="prereq-panel-grid">
                        <div class="prereq-block">
                            <span class="prereq-block-title">{{ __('Requires') }}</span>
                            <div class="prereq-chip-list">
                                @forelse ($requiredCourseIds as $requiredId)
                                @php $requiredInfo = $linkedCourseInfo[$requiredId] ?? null; @endphp
                                <span class="prereq-chip" wire:key="requires-{{ $courseLink['course_id'] }}-{{ $requiredId }}">
                                    <span>{{ $requiredInfo['code'] ?? $requiredId }}</span>
                                    @if ($canEditPlan)
                                    <button type="button" wire:click="removePrerequisite('{{ $requiredId }}-{{ $courseLink['course_id'] }}')" aria-label="{{ __('Remove') }}">&times;</button>
                                    @endif
                                </span>
                                @empty
                                <span class="prereq-empty">{{ __('None') }}</span>
                                @endforelse
                            </div>

                            @if ($canEditPlan)
                                @if ($availableRequiredOptions->isNotEmpty())
                                <x-ui.local-course-combobox
                                    id="addPrereqFor{{ $courseLink['course_id'] }}"
                                    :options="$availableRequiredOptions"
                                    select-action="addPrerequisiteFor"
                                    :action-args="[$courseLink['course_id']]"
                                    placeholder="{{ __('Add a required course...') }}"
                                    style="width: 100%;"
                                />
                                @else
                                <p class="prereq-empty">{{ __('No course from an earlier level of this plan is available yet to set as a prerequisite.') }}</p>
                                @endif
                            @endif
                        </div>

                        <div class="prereq-block">
                            <span class="prereq-block-title">{{ __('Is a prerequisite of') }}</span>
                            <div class="prereq-chip-list">
                                @forelse ($requiredByCourseIds as $dependentId)
                                @php $dependentInfo = $linkedCourseInfo[$dependentId] ?? null; @endphp
                                <span class="prereq-chip muted" wire:key="required-by-{{ $courseLink['course_id'] }}-{{ $dependentId }}">
                                    <span>{{ $dependentInfo['code'] ?? $dependentId }}</span>
                                </span>
                                @empty
                                <span class="prereq-empty">{{ __('None') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @empty
                <div class="empty-row">{{ __('No courses linked to this level yet') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
