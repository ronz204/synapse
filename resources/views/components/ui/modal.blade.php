@props([
'show' => false,
'title' => '',
])

{{--
    Generic modal chrome, reused by every CRUD's create/edit form. Only the body
    (form fields) and the footer (action buttons) change per module; the
    backdrop, header and close button stay identical everywhere.

    Expects the owning Livewire component to expose a closeModal() method.
--}}
<div class="modal-backdrop {{ $show ? 'open' : '' }}">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-head">
            <span class="modal-title">{{ $title }}</span>
            <button type="button" class="modal-close" wire:click="closeModal" aria-label="{{ __('Close') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            {{ $slot }}
        </div>
        <div class="modal-footer">
            {{ $footer }}
        </div>
    </div>
</div>
