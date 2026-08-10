@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => __('Sign in with a passkey'),
    'loadingLabel' => __('Authenticating...'),
    'separator' => __('Or continue with email'),
])

@assets
    @vite('resources/js/passkeys.js')
@endassets

{{--
    Renders nothing when the browser has no WebAuthn support, so the password
    form below it stays the only path on those devices.
--}}
<div
    x-data="{
        supported: false,
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();

            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async verify() {
            this.loading = true;
            this.error = null;

            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: '{{ route($optionsRoute) }}',
                        submit: '{{ route($submitRoute) }}',
                    },
                });

                Livewire.navigate(response.redirect || '{{ route('dashboard') }}');
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
    }">

    <template x-if="supported">
        <div>
            <button
                type="button"
                x-on:click="verify()"
                x-bind:disabled="loading"
                class="flex w-full items-center justify-center gap-2 rounded-lg border border-border-strong bg-background-surface py-3 text-[14.5px] font-semibold text-text-primary transition-colors hover:border-border-brand hover:text-text-brand disabled:opacity-60">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4" />
                    <path d="M14 13.12c0 2.38 0 6.38-1 8.88" />
                    <path d="M17.29 21.02c.12-.6.43-2.3.5-3.02" />
                    <path d="M2 12a10 10 0 0 1 18-6" />
                    <path d="M2 16h.01" />
                    <path d="M21.8 16c.2-2 .131-5.354 0-6" />
                    <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2" />
                    <path d="M8.65 22c.21-.66.45-1.32.57-2" />
                    <path d="M9 6.8a6 6 0 0 1 9 5.2v2" />
                </svg>
                <span x-show="!loading">{{ $label }}</span>
                <span x-show="loading" x-cloak>{{ $loadingLabel }}</span>
            </button>

            <p x-show="error" x-text="error" x-cloak class="mt-2 text-center text-[13px] text-status-danger"></p>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-border-default"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-background-surface px-2 text-text-tertiary">{{ $separator }}</span>
                </div>
            </div>
        </div>
    </template>
</div>
