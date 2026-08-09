@props([
'successText' => null,
])

{{--
    Reusable delete-confirmation overlay, shared by every CRUD's delete action.
    The delete button in <x-ui.row-actions> opens it through askDelete(id).

    Driven entirely by Alpine state declared on the owning Livewire component's
    root element, which must provide: a `confirmDelete` object with `open` and
    `step`, plus askDelete(), runDelete() and closeDeleteModal().

    runDelete() always calls $wire.delete(...), so any component reusing this
    modal needs a delete(int $id) method under exactly that name.
--}}
<div class="del-overlay" :class="{ 'open': confirmDelete.open }">
    <div class="del-card" role="alertdialog" aria-modal="true">
        <template x-if="confirmDelete.step === 'confirm'">
            <div>
                <div class="del-icon-warn" aria-hidden="true">!</div>
                <p class="del-title">{{ __('Are you sure?') }}</p>
                <p class="del-text">{{ __("You won't be able to revert this!") }}</p>
                <div class="del-actions">
                    <button type="button" class="del-btn-confirm" @click="runDelete()">{{ __('Yes, delete it!') }}</button>
                    <button type="button" class="del-btn-cancel" @click="closeDeleteModal()">{{ __('Cancel') }}</button>
                </div>
            </div>
        </template>

        <template x-if="confirmDelete.step === 'success'">
            <div>
                <div class="del-icon-success" aria-hidden="true">
                    <svg width="38" height="38" viewBox="0 0 52 52">
                        <path d="M14 27l8 8 16-16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="60"></path>
                    </svg>
                </div>
                <p class="del-title">{{ __('Deleted!') }}</p>
                <p class="del-text">{{ $successText ?? __('The record has been deleted.') }}</p>
                <button type="button" class="del-btn-ok" @click="closeDeleteModal()">{{ __('OK') }}</button>
            </div>
        </template>
    </div>
</div>
