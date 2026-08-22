@php
    $student = $history->student();
    $accreditedCount = count($history->accreditedByEquivalency());
@endphp
<div>
    <div class="card">
        <div class="card-head">
            <div>
                <span class="card-title">{{ $student->fullName }}</span>
                <div style="margin-top: 4px;">
                    <span class="status-badge {{ $student->active ? 'system' : 'custom' }}">
                        {{ $student->active ? __('Active') : __('Inactive') }}
                    </span>
                    <span style="margin-left: 8px; color: var(--textMuted);">{{ __('National ID') }}: {{ $student->nationalId }}</span>
                    <span style="margin-left: 8px; color: var(--textMuted);">{{ $accreditedCount }} {{ __('accredited by equivalency') }}</span>
                </div>
            </div>
            <div class="card-actions">
                @can('create', \Src\Curriculum\AcademicHistory\Domain\Entities\StudentAcademicHistory::class)
                <button type="button" class="btn btn-orange" wire:click="openPassedCourseModal">{{ __('Add passed course') }}</button>
                @endcan
                <button type="button" class="btn btn-secondary" wire:click="backToStudents">{{ __('Back to students') }}</button>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 16px;">
        <div class="card-head">
            <span class="card-title">{{ __('Simplified internal history') }}</span>
        </div>

        <div class="table-scroll">
            <div class="table-inner" style="--table-cols: 1fr 2.4fr 1.6fr 1.2fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    <span>{{ __('Code') }}</span>
                    <span>{{ __('Course') }}</span>
                    <span>{{ __('Status') }}</span>
                    <span>{{ __('Resolution') }}</span>
                </div>

                @forelse ($history->entries() as $entry)
                <div class="data-row" role="row" wire:key="entry-{{ $entry->courseCode }}">
                    <span class="font-mono text-xs">{{ $entry->courseCode }}</span>
                    <span>{{ $entry->courseName }}</span>
                    <span>
                        <span class="status-badge {{ $entry->isAccreditedByEquivalency() ? 'custom' : 'system' }}">
                            {{ __(Str::headline($entry->status->name)) }}
                        </span>
                    </span>
                    <span class="font-mono text-xs">{{ $entry->resolutionNumber ?? '—' }}</span>
                </div>
                @empty
                <div class="empty-row">{{ __('This student has no academic records yet') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <x-ui.modal :show="$showModal" :title="__('Add passed course')">
        <div class="form-field">
            <label for="academicHistoryCourse">{{ __('Course') }}</label>
            <x-ui.course-combobox
                id="academicHistoryCourse"
                search-property="courseSearch"
                select-action="selectCourse"
                :options="$courseOptions"
                :has-error="$errors->has('form.courseId')"
            />
            @error('form.courseId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label>{{ __('Status') }}</label>
            <input type="text" value="{{ __('Passed') }}" disabled>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="recordPassedCourse">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
