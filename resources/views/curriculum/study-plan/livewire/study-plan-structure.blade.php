@php
    $program = \App\Models\Program::query()->find($studyPlan->programId());
@endphp
<div>
    <div class="card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ $studyPlan->name() }}</span>
                <div style="margin-top: 4px;">
                    <span class="status-badge {{ $studyPlan->classification() === \App\Enums\PlanClassification::Terminal ? 'custom' : 'system' }}">
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
                @if (Auth::user()->can('update', $studyPlan))
                <button type="button" class="btn btn-orange" wire:click="saveStructure">{{ __('Save structure') }}</button>
                @endif
            </div>
        </div>
    </div>

    @if (Auth::user()->can('update', $studyPlan))
    <div class="card" style="margin-top: 16px;">
        <div class="card-head">
            <span class="card-title">{{ __('Add level') }}</span>
        </div>
        <div class="card-controls">
            <div class="control-group">
                <input type="number" min="1" wire:model="newLevelNumber" placeholder="{{ __('Level number') }}" style="width: 140px;">
                <button type="button" class="btn btn-primary" wire:click="addLevel">{{ __('Add level') }}</button>
            </div>
        </div>
    </div>
    @endif

    @forelse ($structureLevels as $levelIndex => $level)
    <div class="card" style="margin-top: 16px;" wire:key="level-{{ $level['key'] }}">
        <div class="card-head">
            <span class="card-title">{{ __('Level') }} {{ $level['number'] }}</span>
            <div class="card-actions">
                <span class="status-badge system">{{ $activeStudentCounts[$level['number']] ?? 0 }} {{ __('active students') }}</span>
                @if (Auth::user()->can('update', $studyPlan))
                <button type="button" class="btn btn-secondary" wire:click="removeLevel('{{ $level['key'] }}')">{{ __('Remove level') }}</button>
                @endif
            </div>
        </div>

        <div class="table-scroll">
            <div class="table-inner" style="--table-cols: 2fr 1fr 1fr 1fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Name') }}</span>
                    <span>{{ __('Credits') }}</span>
                    <span>{{ __('Actions') }}</span>
                </div>

                @forelse ($level['courses'] as $courseLink)
                @php
                    $courseInfo = collect($courseOptions)->firstWhere('id', $courseLink['course_id']);
                @endphp
                <div class="data-row" role="row" wire:key="course-{{ $level['key'] }}-{{ $courseLink['course_id'] }}">
                    <span class="font-mono text-xs">{{ $courseInfo['code'] ?? $courseLink['course_id'] }}</span>
                    <span>{{ $courseInfo['name'] ?? '—' }}</span>
                    <span>{{ $courseLink['credits'] }}</span>
                    <span>
                        @if (Auth::user()->can('update', $studyPlan))
                        <button type="button" class="action-icon delete" wire:click="removeCourseFromLevel('{{ $level['key'] }}', {{ $courseLink['course_id'] }})" title="{{ __('Remove') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        @endif
                    </span>
                </div>
                @empty
                <div class="empty-row">{{ __('No courses linked to this level yet') }}</div>
                @endforelse
            </div>
        </div>

        @if (Auth::user()->can('update', $studyPlan))
        <div class="card-controls">
            <div class="control-group">
                <select wire:model="newCourseSelection.{{ $level['key'] }}.course_id" style="min-width: 220px;">
                    <option value="">{{ __('Select a course...') }}</option>
                    @foreach ($courseOptions as $course)
                    <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" wire:model="newCourseSelection.{{ $level['key'] }}.credits" placeholder="{{ __('Credits') }}" style="width: 100px;">
                <button type="button" class="btn btn-primary" wire:click="addCourseToLevel('{{ $level['key'] }}')">{{ __('Add course') }}</button>
            </div>
        </div>
        @endif
    </div>
    @empty
    <div class="card" style="margin-top: 16px;">
        <div class="empty-row">{{ __('No levels yet — add one above') }}</div>
    </div>
    @endforelse

    <div class="card" style="margin-top: 16px;">
        <div class="card-head">
            <span class="card-title">{{ __('Prerequisites') }}</span>
        </div>

        <div class="table-scroll">
            <div class="table-inner" style="--table-cols: 2fr 2fr 1fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    <span>{{ __('Required course') }}</span>
                    <span>{{ __('Dependent course') }}</span>
                    <span>{{ __('Actions') }}</span>
                </div>

                @forelse ($structurePrerequisites as $prerequisite)
                @php
                    $requiredInfo = collect($courseOptions)->firstWhere('id', $prerequisite['required_course_id']);
                    $dependentInfo = collect($courseOptions)->firstWhere('id', $prerequisite['dependent_course_id']);
                @endphp
                <div class="data-row" role="row" wire:key="prerequisite-{{ $prerequisite['key'] }}">
                    <span>{{ $requiredInfo['code'] ?? $prerequisite['required_course_id'] }} — {{ $requiredInfo['name'] ?? '' }}</span>
                    <span>{{ $dependentInfo['code'] ?? $prerequisite['dependent_course_id'] }} — {{ $dependentInfo['name'] ?? '' }}</span>
                    <span>
                        @if (Auth::user()->can('update', $studyPlan))
                        <button type="button" class="action-icon delete" wire:click="removePrerequisite('{{ $prerequisite['key'] }}')" title="{{ __('Remove') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        @endif
                    </span>
                </div>
                @empty
                <div class="empty-row">{{ __('No prerequisites registered yet') }}</div>
                @endforelse
            </div>
        </div>

        @if (Auth::user()->can('update', $studyPlan))
        <div class="card-controls">
            <div class="control-group">
                <span>{{ __('Required') }}</span>
                <select wire:model="newPrerequisiteRequired" style="min-width: 200px;">
                    <option value="">{{ __('Select a course...') }}</option>
                    @foreach ($courseOptions as $course)
                    <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                    @endforeach
                </select>
                <span>{{ __('Dependent') }}</span>
                <select wire:model="newPrerequisiteDependent" style="min-width: 200px;">
                    <option value="">{{ __('Select a course...') }}</option>
                    @foreach ($courseOptions as $course)
                    <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-primary" wire:click="addPrerequisite">{{ __('Add prerequisite') }}</button>
            </div>
        </div>
        @endif
    </div>
</div>
