<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body>
    <div class="app" x-data="{
        mobileOpen: false,
        collapsed: false,

        {{-- Read from the class Flux already put on <html> before paint, so the toggle
             starts in sync and the page never flashes the wrong theme. --}}
        dark: document.documentElement.classList.contains('dark'),

        fontLevel: localStorage.getItem('fontLevel') || 'a',
        init() {
            this.updateZoom(this.fontLevel);
        },

        toggleDark() {
            this.dark = !this.dark;

            {{-- Flux owns appearance, and the Settings > Appearance screen writes the
                 same state. Go through its API when it is loaded so both stay in
                 agreement; fall back to the class directly if it is not. --}}
            if (window.Flux) {
                window.Flux.appearance = this.dark ? 'dark' : 'light';
            } else {
                document.documentElement.classList.toggle('dark', this.dark);
            }
        },

        setFont(level) {
            this.fontLevel = level;
            localStorage.setItem('fontLevel', level);
            this.updateZoom(level);
        },

        updateZoom(level) {
            const zoom = level === 'aaa' ? 1.3 : level === 'aa' ? 1.15 : 1;
            document.documentElement.style.setProperty('--font-zoom', zoom);
        }
    }">

        <div class="backdrop" :class="{ 'show': mobileOpen }" @click="mobileOpen = false"></div>

        {{--
            Persistence is declared with the `x-persist` attribute on <aside> inside the
            component itself, not with @persist/@endpersist here. That directive wraps its
            content in an extra <div x-persist="sidebar">, which then becomes the real flex
            child of .app instead of <aside> — breaking align-items: stretch, so the
            sidebar's background stops reaching the bottom of the viewport. The attribute
            form keeps the same DOM node (and its Alpine state) across wire:navigate
            without the extra wrapper. See livewire/livewire#5936.
        --}}
        <x-siga.sidebar />

        <div class="main">
            <x-siga.topbar :title="$title ?? null" :subtitle="$subtitle ?? null" />

            <main class="content">
                {{ $slot }}
            </main>

            <footer class="app-footer">
                <span>© {{ date('Y') }} {{ __('National Technical University') }}. {{ __('All rights reserved.') }}</span>
                <span>{{ __('Regional Campus San Carlos') }} · <a href="https://www.utn.ac.cr" target="_blank" rel="noopener">www.utn.ac.cr</a></span>
            </footer>
        </div>
    </div>

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
