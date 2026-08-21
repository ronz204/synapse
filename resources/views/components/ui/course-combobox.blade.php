@props([
    'id',
    'searchProperty',
    'selectAction',
    'options',
    'hasError' => false,
    'actionArgs' => [],
])

{{--
    Server-searched course picker — see App\Livewire\Concerns\InteractsWithCourseSearch
    for why this replaced a plain <select> looping over every active course.
    $searchProperty is bound live (debounced) so typing re-renders the
    component and $options comes back re-computed for the current term,
    never more than a handful of rows regardless of catalog size.
    $selectAction is called with (...$actionArgs, id, code, name) when a
    result is clicked — $actionArgs carries any fixed context the action
    needs ahead of the picked course (e.g. StudyPlan's per-level key), and
    is empty for a single, unqualified picker like Equivalency's source/
    target course.
--}}
<div {{ $attributes->class(['course-combobox']) }} x-data="{ open: false }" @click.outside="open = false">
    <input
        type="text"
        id="{{ $id }}"
        wire:model.live.debounce.300ms="{{ $searchProperty }}"
        @focus="open = true"
        placeholder="{{ __('Search course by code or name...') }}"
        autocomplete="off"
        class="{{ $hasError ? 'has-error' : '' }}"
    >
    <div class="course-combobox-results" x-show="open" x-cloak>
        @forelse ($options as $course)
        @php
            $callArgs = collect($actionArgs)
                ->map(fn ($arg) => "'".addslashes((string) $arg)."'")
                ->push($course['id'])
                ->push("'".addslashes($course['code'])."'")
                ->push("'".addslashes($course['name'])."'")
                ->implode(', ');
        @endphp
        <button
            type="button"
            class="course-combobox-option"
            wire:click="{{ $selectAction }}({{ $callArgs }})"
            @click="open = false"
        >
            <span class="course-combobox-code">{{ $course['code'] }}</span>
            <span class="course-combobox-name">{{ $course['name'] }}</span>
        </button>
        @empty
        <div class="course-combobox-empty">{{ __('No results') }}</div>
        @endforelse
    </div>
</div>
