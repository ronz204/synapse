<x-layouts::auth.siga :title="__('Reset password')">
    <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Reset password') }}</h2>
    <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('Please enter your new password below') }}</p>

    <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf

        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <x-siga.field
            name="email"
            type="email"
            :label="__('Email address')"
            :value="request('email')"
            required
            autocomplete="email" />

        <x-siga.field
            name="password"
            type="password"
            :label="__('Password')"
            :rules="\Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString()"
            required
            autofocus
            autocomplete="new-password"
            placeholder="••••••••" />

        <x-siga.field
            name="password_confirmation"
            type="password"
            :label="__('Confirm password')"
            :rules="\Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString()"
            required
            autocomplete="new-password"
            placeholder="••••••••" />

        <button type="submit" class="mt-1 bg-background-brand-default hover:bg-background-brand-hover text-text-inverse py-3 rounded-lg text-[15px] font-bold transition-colors">
            {{ __('Reset password') }}
        </button>
    </form>
</x-layouts::auth.siga>
