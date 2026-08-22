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
                    <span class="status-badge custom" x-show="row.active">{{ __('Active') }}</span>
                    <span class="status-badge muted" x-show="!row.active">{{ __('Inactive') }}</span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('courses.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('courses.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-visible="row.active"
                        delete-id="row.id" />
                    @if (Auth::user()->hasPermissionTo('courses.delete'))
                    <button type="button" class="action-icon activate" x-show="!row.active" @click="$wire.activate(row.id)" title="{{ __('Activate') }}" aria-label="{{ __('Activate') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                            <path d="M3 3v5h5"></path>
                        </svg>
                    </button>
                    @endif
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
                <span class="status-badge custom">{{ __('Active') }}</span>
                @else
                <span class="status-badge muted">{{ __('Inactive') }}</span>
                @endif
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $course)"
                    :can-delete="Auth::user()->can('delete', $course) && $course->isActive()"
                    edit-action="$wire.openEditModal({{ $course->id() }})"
                    delete-id="{{ $course->id() }}" />
                @if (! $course->isActive() && Auth::user()->can('activate', $course))
                <button type="button" class="action-icon activate" wire:click="activate({{ $course->id() }})" title="{{ __('Activate') }}" aria-label="{{ __('Activate') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                    </svg>
                </button>
                @endif
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.pdf-export-status :id="$pdfExportId" :status="$pdfExportStatus" />

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
                <option value="{{ $type->value }}">{{ __(Str::headline($type->name)) }}</option>
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
