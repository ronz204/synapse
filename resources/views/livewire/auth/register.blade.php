<x-layouts::auth.siga :title="__('Register')">
    <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Create an account') }}</h2>
    <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('Enter your details below to create your account') }}</p>

    <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4">
        @csrf

        <x-siga.field
            name="name"
            :label="__('Name')"
            required
            autofocus
            autocomplete="name"
            :placeholder="__('Full name')" />

        <x-siga.field
            name="email"
            type="email"
            :label="__('Institutional Email')"
            required
            autocomplete="email"
            placeholder="nombre.apellido@utn.ac.cr" />

        <x-siga.field
            name="password"
            type="password"
            :label="__('Password')"
            :rules="\Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString()"
            required
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
            {{ __('Create account') }}
        </button>
    </form>

    <p class="mt-5 text-center text-[13px] text-text-secondary">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-text-brand hover:text-background-brand-hover transition-colors">{{ __('Log in') }}</a>
    </p>
</x-layouts::auth.siga>
