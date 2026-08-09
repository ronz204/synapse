<x-layouts::auth.siga :title="__('Forgot password')">
    <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Forgot your password?') }}</h2>
    <p class="text-[13.5px] text-text-secondary mb-6 text-center leading-relaxed">
        {{ __('Enter your institutional email and we will send you a link to reset it') }}
    </p>

    @if (session('status'))
        <div class="bg-background-brand-subtle border border-border-brand/20 rounded-[10px] py-3.5 px-4 mb-5 text-sm text-text-brand leading-relaxed">
            {{ __('We sent a reset link to') }} <strong>{{ session('email', old('email')) }}</strong>. {{ __('Check your inbox.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf

        <label class="flex flex-col gap-1.5">
            <span class="text-[13px] font-semibold text-text-primary">{{ __('Email address') }}</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nombre.apellido@utn.ac.cr" class="px-3.5 py-3 border border-border-strong rounded-lg text-[14.5px] outline-none focus:border-border-focus focus:ring-3 focus:ring-border-focus/10 transition-all">
            @error('email')
                <span class="text-[13px] text-status-danger">{{ $message }}</span>
            @enderror
        </label>

        <button type="submit" class="mt-1 bg-background-brand-default hover:bg-background-brand-hover text-text-inverse py-3 rounded-lg text-[15px] font-bold transition-colors">
            {{ __('Send reset link') }}
        </button>
    </form>

    <p class="mt-5 text-center text-[13px] text-text-secondary">
        {{ __('Remembered it?') }}
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-text-brand hover:text-background-brand-hover transition-colors">{{ __('Log in') }}</a>
    </p>
</x-layouts::auth.siga>
