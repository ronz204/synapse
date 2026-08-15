<div x-data="{
    confirmDelete: { open: false, step: 'confirm', id: null },
    askDelete(id) {
        this.confirmDelete = { open: true, step: 'confirm', id };
    },
    runDelete() {
        $wire.delete(this.confirmDelete.id)
            .then(() => { this.confirmDelete.step = 'success'; })
            .catch(() => { this.confirmDelete.open = false; });
    },
    closeDeleteModal() {
        this.confirmDelete.open = false;
    },
}">
    <x-ui.data-table
        :headers="[
                ['key' => 'code', 'label' => __('Code'), 'sortable' => true],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'isService', 'label' => __('Service'), 'sortable' => false],
                ['key' => 'modalityName', 'label' => __('Modality'), 'sortable' => false],
                ['key' => 'active', 'label' => __('Status'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['code', 'name']"
        :paginator="$courses ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 2fr 1fr 1fr 1fr 0.8fr"
        :can-create="Auth::user()->can('create', \Src\Curriculum\Course\Domain\Entities\Course::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Curriculum\Course\Domain\Entities\Course::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Curriculum\Course\Domain\Entities\Course::class)"
        :title="__('Course catalog')">

        @if ($tableMode === 'client')
        {{-- Client mode: Alpine renders rows from the in-browser `pageRows`
                     collection. See resources/js/data-table.js. --}}
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span class="font-mono text-xs" x-text="row.code"></span>
                <span x-text="row.name"></span>
                <span>
                    <span class="status-badge system" x-show="row.isService">{{ __('Service') }}</span>
                    <span class="status-badge custom" x-show="!row.isService">{{ __('Program') }}</span>
                </span>
                <span x-text="row.modalityName ?? '{{ __('Default (Presencial)') }}'"></span>
                <span>
                    <span class="status-badge system" x-show="row.active">{{ __('Active') }}</span>
                    <span class="status-badge custom" x-show="!row.active">{{ __('Inactive') }}</span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('courses.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('courses.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-visible="row.active"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        {{-- Server mode: unchanged Livewire-driven pagination. --}}
        @forelse ($courses as $course)
        <div class="data-row" role="row">
            <span class="font-mono text-xs">{{ $course->code() }}</span>
            <span>{{ $course->name() }}</span>
            <span>
                @if ($course->isService())
                <span class="status-badge system">{{ __('Service') }}</span>
                @else
                <span class="status-badge custom">{{ __('Program') }}</span>
                @endif
            </span>
            <span>{{ $modalityNames[$course->modalityId()] ?? __('Default (Presencial)') }}</span>
            <span>
                @if ($course->isActive())
                <span class="status-badge system">{{ __('Active') }}</span>
                @else
                <span class="status-badge custom">{{ __('Inactive') }}</span>
                @endif
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $course)"
                    :can-delete="Auth::user()->can('delete', $course) && $course->isActive()"
                    edit-action="$wire.openEditModal({{ $course->id() }})"
                    delete-id="{{ $course->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New course') : __('Edit course')">
        <div class="form-field">
            <label for="courseCode">{{ __('Code') }}</label>
            <input type="text" id="courseCode" wire:model="form.code" placeholder="{{ __('E.g. INF-101') }}" class="{{ $errors->has('form.code') ? 'has-error' : '' }}">
            @error('form.code') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="courseName">{{ __('Name') }}</label>
            <input type="text" id="courseName" wire:model="form.name" placeholder="{{ __('E.g. Introduction to Programming') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label>
                <input type="checkbox" wire:model.live="form.isService">
                {{ __('This is a shared service course (no owning program)') }}
            </label>
        </div>

        @if (! $form->isService)
        <div class="form-field">
            <label for="courseProgram">{{ __('Program') }}</label>
            <select id="courseProgram" wire:model="form.programId" class="{{ $errors->has('form.programId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a program...') }}</option>
                @foreach ($programOptions as $program)
                <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                @endforeach
            </select>
            @error('form.programId') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        @endif

        @if ($editingId !== null)
        <div class="form-field">
            <label>{{ __('Modality') }}</label>
            <p class="text-sm">
                {{ $modalityNames[$editingModalityId] ?? __('Default (Presencial)') }} —
                <a href="{{ route('curriculum.modality_assignment.index') }}" wire:navigate>{{ __('Reassign modality') }}</a>
            </p>
        </div>
        @endif

        <div class="form-field">
            <label>
                <input type="checkbox" wire:model="form.isBottleneck">
                {{ __('Bottleneck course') }}
            </label>
        </div>

        <div class="form-field">
            <label>
                <input type="checkbox" wire:model.live="form.requiresLaboratory">
                {{ __('Requires a laboratory') }}
            </label>
        </div>

        @if ($form->requiresLaboratory)
        <div class="form-field">
            <label for="courseLabType">{{ __('Laboratory type') }}</label>
            <select id="courseLabType" wire:model="form.laboratoryType" class="{{ $errors->has('form.laboratoryType') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a laboratory type...') }}</option>
                @foreach ($laboratoryTypeOptions as $type)
                <option value="{{ $type->value }}">{{ $type->value }}</option>
                @endforeach
            </select>
            @error('form.laboratoryType') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        @endif

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The course has been deactivated.')" />
</div>
