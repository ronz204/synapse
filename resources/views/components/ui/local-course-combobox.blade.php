@props([
    'id',
    'options',
    'selectAction',
    'actionArgs' => [],
    'placeholder' => null,
])

{{--
    Client-side-filtered course picker — see resources/js/local-course-combobox.js
    for why this exists alongside <x-ui.course-combobox>: $options is the
    full candidate list (already small and known ahead of time), filtered
    entirely in the browser as the user types, no Livewire round trip.
    Choosing a result both is the action: it calls $selectAction directly
    with (...$actionArgs, course.id) and clears itself — there is no
    separate "confirm" step, unlike <x-ui.course-combobox> which only fills
    in a value for a form field submitted later.
--}}
<div
    {{ $attributes->class(['course-combobox']) }}
    x-data="localCourseCombobox({ options: @js($options) })"
    @click.outside="open = false"
>
    <input
        x-ref="input"
        type="text"
        id="{{ $id }}"
        x-model="query"
        @focus="openDrop()"
        placeholder="{{ $placeholder ?? __('Search course by code or name...') }}"
        autocomplete="off"
    >
    <div
        class="course-combobox-results course-combobox-results--fixed"
        x-show="open"
        x-cloak
        :style="`top:${dropTop}px;left:${dropLeft}px;width:${dropWidth}px`"
    >
        <template x-for="course in filtered" :key="course.id">
            <button
                type="button"
                class="course-combobox-option"
                @click="choose(course, (c) => $wire.{{ $selectAction }}({{ collect($actionArgs)->map(fn ($arg) => is_int($arg) || is_float($arg) ? (string) $arg : "'".addslashes((string) $arg)."'")->push('c.id')->implode(', ') }}))"
            >
                <span class="course-combobox-code" x-text="course.code"></span>
                <span class="course-combobox-name" x-text="course.name"></span>
            </button>
        </template>
        <div class="course-combobox-empty" x-show="filtered.length === 0">{{ __('No results') }}</div>
    </div>
</div>
