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

    <label class="flex flex-col gap-1.5">
        <span class="text-[13px] font-semibold text-text-primary">{{ __('Institutional Email') }}</span>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nombre.apellido@utn.ac.cr" class="px-3.5 py-3 border border-border-strong rounded-lg text-[14.5px] outline-none focus:border-border-focus focus:ring-3 focus:ring-border-focus/10 transition-all">
    </label>

    <label class="flex flex-col gap-1.5" x-data="{ show: false }">
        <span class="text-[13px] font-semibold text-text-primary">{{ __('Password') }}</span>
        <div class="relative flex items-center">
            <input :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="••••••••" class="w-full px-3.5 py-3 pr-10 border border-border-strong rounded-lg text-[14.5px] outline-none focus:border-border-focus focus:ring-3 focus:ring-border-focus/10 transition-all">

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
    </label>

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
