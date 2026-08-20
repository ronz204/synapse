<div>
    <x-ui.data-table
        :headers="[
                ['key' => 'national_id', 'label' => __('National ID'), 'sortable' => true],
                ['key' => 'first_last_name', 'label' => __('Student'), 'sortable' => true],
                ['key' => 'active', 'label' => __('Status'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :paginator="$students"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 2fr 1fr 1fr"
        :title="__('Academic history')">

        @forelse ($students as $student)
        <div class="data-row" role="row" wire:key="student-{{ $student->id }}">
            <span class="font-mono text-xs">{{ $student->nationalId }}</span>
            <span>{{ $student->fullName }}</span>
            <span>
                @if ($student->active)
                <span class="status-badge system">{{ __('Active') }}</span>
                @else
                <span class="status-badge custom">{{ __('Inactive') }}</span>
                @endif
            </span>
            <div class="actions-cell">
                <button type="button" class="btn btn-secondary" wire:click="viewHistory({{ $student->id }})">{{ __('View history') }}</button>
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
    </x-ui.data-table>
</div>
