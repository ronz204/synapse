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
                        {{-- The enum's stored value, in Spanish, is what RC-02b
                             names verbatim as the mark a course must carry. --}}
                        <span class="status-badge {{ $entry->isAccreditedByEquivalency() ? 'custom' : 'system' }}">
                            {{ $entry->status->value }}
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
</div>
