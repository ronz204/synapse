@props([
'title' => null,
'heading' => null,
])

{{--
    Institutional shell for the standalone auth screens. Deliberately does not
    force class="dark" on <html> the way layouts/auth/simple does — appearance is
    Flux's to decide, and these pages read the same tokens as the rest of the app.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-background-page antialiased">
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <x-siga.auth-panel :heading="$heading">
            {{ $slot }}
        </x-siga.auth-panel>

        <a href="{{ route('home') }}" wire:navigate class="text-[13px] font-semibold text-text-tertiary hover:text-text-brand transition-colors">
            {{ __('Back to home') }}
        </a>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
