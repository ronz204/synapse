@props([
'canEdit' => false,
'canDelete' => false,
'editAction' => null,
'deleteId' => null,
'deleteVisible' => 'true',
])

{{--
    Actions go through Alpine's $wire magic (@click="$wire.foo(...)") instead of
    wire:click, so the same markup works whether the row comes from a server-side
    @forelse (a PHP-interpolated id) or a client-side <template x-for> (row.id).
    $wire resolves the nearest Livewire root regardless of local x-data, so no
    extra wrapper is needed here.

    Edit and Delete only — Roles and Permissions have no read-only detail screen,
    so the design's view icon is left out until something needs it.

    Delete opens <x-ui.confirm-delete-modal> through askDelete(id) rather than a
    native confirm() dialog. `deleteId` is the raw id expression: 'row.id' in
    client mode, '{{ $entity->id() }}' in server mode.
--}}
@if ($canEdit && $editAction)
<button type="button" class="action-icon edit" @click="{{ $editAction }}" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"></path>
    </svg>
</button>
@endif

@if ($canDelete && $deleteId)
<button type="button" class="action-icon delete" x-show="{{ $deleteVisible }}" @click="askDelete({{ $deleteId }})" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"></polyline>
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
        <path d="M10 11v6"></path>
        <path d="M14 11v6"></path>
        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
    </svg>
</button>
@endif
