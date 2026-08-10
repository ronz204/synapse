@props([
'heading' => null,
])

{{--
    The institutional card: brand strip, UTN medallion and white body. Shared by
    the landing page's <x-siga.auth-card> and by every standalone auth screen
    through <x-layouts::auth.siga>, so the two never drift apart.
--}}
<div {{ $attributes->merge(['class' => 'w-full max-w-[380px] mx-auto relative z-10']) }}>
    <div class="bg-background-surface rounded-[18px] shadow-[0_30px_60px_-24px_rgba(11,61,120,0.3)] border border-border-default overflow-hidden">

        <div class="bg-background-brand-default pt-7 px-8 pb-11 relative text-center">
            <div class="font-black text-lg text-text-inverse">{{ $heading ?? __('National Technical University') }}</div>
        </div>

        {{-- The medallion stays white in both themes: the UTN mark is dark ink and
             all but disappears against the dark-mode surface. --}}
        <div class="w-16 h-16 rounded-full bg-neutral-0 border-4 border-neutral-0 -mt-8 mx-auto flex items-center justify-center relative shadow-[0_4px_10px_rgba(11,61,120,0.15)] overflow-hidden">
            <img src="{{ asset('images/logo-utn.avif') }}" alt="UTN" class="w-[70%] h-[70%] object-contain">
        </div>

        <div class="p-5 md:px-8 md:pb-8 md:pt-5">
            {{ $slot }}
        </div>
    </div>
</div>
