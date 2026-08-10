@props([
'name',
'label',
'type' => 'text',
'value' => null,
'rules' => null,
])

@php
    $isPassword = $type === 'password';
    $control = 'w-full px-3.5 py-3 border border-border-strong rounded-lg text-[14.5px] text-text-primary bg-background-surface outline-none focus:border-border-focus focus:ring-3 focus:ring-border-focus/10 transition-all';
@endphp

{{--
    One field for every institutional form: label, control and error message.
    Password fields get a show/hide toggle; everything else renders a plain
    control. Extra attributes (required, autofocus, autocomplete, placeholder)
    pass straight through to the input.
--}}
<label class="flex flex-col gap-1.5" @if ($isPassword) x-data="{ show: false }" @endif>
    <span class="text-[13px] font-semibold text-text-primary">{{ $label }}</span>

    @if ($isPassword)
        <div class="relative flex items-center">
            <input
                :type="show ? 'text' : 'password'"
                name="{{ $name }}"
                @if ($rules) passwordrules="{{ $rules }}" @endif
                {{ $attributes->merge(['class' => $control.' pr-10']) }}>

            <button type="button" @click="show = !show" class="absolute right-2.5 p-1 text-text-tertiary hover:text-text-brand transition-colors" aria-label="{{ __('Show or hide password') }}">
                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M3 3l18 18"></path>
                    <path d="M10.6 10.6a3 3 0 0 0 4.24 4.24"></path>
                    <path d="M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.9 13.9 0 0 1-2.9 3.9M6.4 6.4C4.3 7.8 2.7 9.9 2 12c0 0 3.5 7 10 7 1 0 1.9-.1 2.8-.4"></path>
                </svg>
            </button>
        </div>
    @else
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $value ?? old($name) }}" {{ $attributes->merge(['class' => $control]) }}>
    @endif

    @error($name)
        <span class="text-[13px] text-status-danger">{{ $message }}</span>
    @enderror
</label>
