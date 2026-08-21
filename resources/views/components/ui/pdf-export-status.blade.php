@props([
    'id' => null,
    'status' => null,
])

{{--
    Floating snackbar for the queued-PDF-export flow (see performance.md P1 and
    App\Livewire\Concerns\InteractsWithExports) — one line include in every
    catalog view instead of duplicating this markup in all 7. wire:poll only
    runs while this element is in the DOM, which only happens while $id is
    set, so polling stops on its own once the export is downloaded or
    dismissed and the next render omits this block entirely.

    Fixed to the viewport corner on purpose: this used to render inline right
    after the table, which meant a long catalog required scrolling all the way
    down just to see whether the export was ready. A snackbar stays visible
    regardless of scroll position or which page of results is showing.
--}}
@if ($id !== null)
<div
    class="pdf-export-toast"
    data-variant="{{ $status ?? 'pending' }}"
    role="status"
    aria-live="polite"
    @if ($status === 'pending') wire:poll.2s="checkPdfExportStatus" @endif
>
    <span class="pdf-export-toast-icon">
        @if ($status === 'ready')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm3.844-8.791a.75.75 0 0 0-1.188-.918l-3.7 4.79-1.649-1.833a.75.75 0 1 0-1.114 1.004l2.25 2.5a.75.75 0 0 0 1.15-.043l4.25-5.5Z" clip-rule="evenodd" />
            </svg>
        @elseif ($status === 'failed')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                <path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" class="size-4 animate-spin motion-reduce:animate-none">
                <circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-dasharray="28 39" />
            </svg>
        @endif
    </span>

    <span class="pdf-export-toast-text">
        @if ($status === 'ready')
            {{ __('Your PDF export is ready.') }}
        @elseif ($status === 'failed')
            {{ __('The PDF export could not be generated. Please try again.') }}
        @else
            {{ __('Generating your PDF export — this can take a few seconds...') }}
        @endif
    </span>

    <span class="pdf-export-toast-actions">
        @if ($status === 'ready')
            <button type="button" class="btn btn-primary" wire:click="downloadQueuedPdf">{{ __('Download') }}</button>
        @endif
        @if ($status === 'ready' || $status === 'failed')
            <button type="button" class="pdf-export-toast-dismiss" wire:click="dismissPdfExportNotice" aria-label="{{ __('Dismiss') }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        @endif
    </span>
</div>
@endif
