@props([
'rows' => 6,
'tableCols' => '1fr',
])

{{--
    Loading placeholder shaped like the table it replaces.

    Deliberately NOT a spinner. FR-003 asks for a state that anticipates the
    shape of what is coming, and a centred spinner tells the user nothing about
    that — the same wait reads as longer. The bars below reuse the real grid
    (--table-cols) and the real row padding, so the content lands where the
    placeholder already was instead of shifting the page.

    Aria-hidden with a polite live region alongside: the bars are decorative,
    and a screen reader should hear "loading", not eleven empty cells.
--}}
<div class="skeleton-table" aria-hidden="true" style="--table-cols: {{ $tableCols }};">
    @for ($row = 0; $row < (int) $rows; $row++)
        <div class="skeleton-row">
            @foreach (explode(' ', $tableCols) as $column)
                <span class="skeleton-bar"></span>
            @endforeach
        </div>
    @endfor
</div>

<span class="sr-only" role="status" aria-live="polite">{{ __('Loading records...') }}</span>
