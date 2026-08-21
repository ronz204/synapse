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
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'requiresResolution', 'label' => __('Requires resolution'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['name']"
        :paginator="$modalities ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="2fr 1.5fr 0.8fr"
        :can-create="Auth::user()->can('create', \Src\Curriculum\Modality\Domain\Entities\Modality::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Curriculum\Modality\Domain\Entities\Modality::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Curriculum\Modality\Domain\Entities\Modality::class)"
        :title="__('Modality catalog')">

        @if ($tableMode === 'client')
        {{-- Client mode: Alpine renders rows from the in-browser `pageRows`
                     collection. See resources/js/data-table.js. --}}
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span x-text="row.name"></span>
                <span>
                    <span class="status-badge system" x-show="row.requiresResolution">{{ __('Yes') }}</span>
                    <span class="status-badge custom" x-show="!row.requiresResolution">{{ __('No') }}</span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('modalities.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('modalities.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        {{-- Server mode: unchanged Livewire-driven pagination. --}}
        @forelse ($modalities as $modality)
        <div class="data-row" role="row">
            <span>{{ $modality->name() }}</span>
            <span>
                @if ($modality->requiresResolution())
                <span class="status-badge system">{{ __('Yes') }}</span>
                @else
                <span class="status-badge custom">{{ __('No') }}</span>
                @endif
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $modality)"
                    :can-delete="Auth::user()->can('delete', $modality)"
                    edit-action="$wire.openEditModal({{ $modality->id() }})"
                    delete-id="{{ $modality->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.pdf-export-status :id="$pdfExportId" :status="$pdfExportStatus" />

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New modality') : __('Edit modality')">
        <div class="form-field">
            <label for="modalityName">{{ __('Name') }}</label>
            <input type="text" id="modalityName" wire:model="form.name" placeholder="{{ __('E.g. Virtual') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label>
                <input type="checkbox" wire:model="form.requiresResolution">
                {{ __('Requires a resolution on file to be assigned to a course') }}
            </label>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The modality has been deleted.')" />
</div>
