<x-layouts::auth.siga :title="__('Log in')">
    <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Log in') }}</h2>
    <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('UTN Institutional Account') }}</p>

    <x-siga.login-form />

    @if (Route::has('register'))
        <p class="mt-5 text-center text-[13px] text-text-secondary">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register') }}" wire:navigate class="font-semibold text-text-brand hover:text-background-brand-hover transition-colors">{{ __('Sign up') }}</a>
        </p>
    @endif
</x-layouts::auth.siga>
