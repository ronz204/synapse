{{--
    The institutional sign-in form. Lives on its own because it appears twice:
    embedded in the landing page through <x-siga.auth-card>, and standalone at
    /login. Posts straight to Fortify — no Livewire component backs it.
--}}
<x-auth-session-status class="mb-4 text-center" :status="session('status')" />

@if ($errors->any())
    <div class="mb-4 text-sm text-status-danger text-center">{{ __('These credentials do not match our records.') }}</div>
@endif

<form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
    @csrf

    <x-siga.field
        name="email"
        type="email"
        :label="__('Institutional Email')"
        required
        autofocus
        autocomplete="email"
        placeholder="nombre.apellido@utn.ac.cr" />

    <x-siga.field
        name="password"
        type="password"
        :label="__('Password')"
        required
        autocomplete="current-password"
        placeholder="••••••••" />

    @if (Route::has('password.request'))
        <div class="flex justify-end -mt-1">
            <a href="{{ route('password.request') }}" wire:navigate class="text-[13px] font-semibold text-text-brand hover:text-background-brand-hover transition-colors">
                {{ __('Forgot your password?') }}
            </a>
        </div>
    @endif

    <button type="submit" class="mt-1 bg-background-brand-default hover:bg-background-brand-hover text-text-inverse py-3 rounded-lg text-[15px] font-bold transition-colors">
        {{ __('Sign in') }}
    </button>
</form>
