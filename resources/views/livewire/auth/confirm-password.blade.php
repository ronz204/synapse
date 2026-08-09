<x-layouts::auth.siga :title="__('Confirm password')">
    <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Confirm password') }}</h2>
    <p class="text-[13.5px] text-text-secondary mb-6 text-center leading-relaxed">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <x-auth-session-status class="mb-4 text-center" :status="session('status')" />

    <x-passkey-verify
        options-route="passkey.confirm-options"
        submit-route="passkey.confirm"
        :label="__('Confirm with passkey')"
        :loading-label="__('Confirming...')"
        :separator="__('Or confirm with password')" />

    <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-4">
        @csrf

        <x-siga.field
            name="password"
            type="password"
            :label="__('Password')"
            required
            autofocus
            autocomplete="current-password"
            placeholder="••••••••" />

        <button type="submit" class="mt-1 bg-background-brand-default hover:bg-background-brand-hover text-text-inverse py-3 rounded-lg text-[15px] font-bold transition-colors">
            {{ __('Confirm') }}
        </button>
    </form>
</x-layouts::auth.siga>
