@php $title = __('Comprehensive Academic Management System'); @endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="landing-canvas flex flex-col min-h-screen">

    <x-siga.header />

    <main class="flex-1 grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] items-center gap-10 px-5 md:px-14 max-w-7xl mx-auto w-full relative">
        {{-- Oversized initial used as a watermark behind the hero. --}}
        <span aria-hidden="true" class="absolute left-[-20px] lg:left-[-40px] top-1/2 -translate-y-1/2 font-black text-[200px] lg:text-[340px] leading-none text-text-brand opacity-5 pointer-events-none select-none z-0">S</span>

        <x-siga.hero />
        <x-siga.auth-card />
    </main>

    <x-siga.footer />

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
