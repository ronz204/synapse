<div class="space-y-6 rounded-xl border border-border-default py-6 shadow-elevation-1" wire:cloak x-data="{ showRecoveryCodes: false }">
    <div class="space-y-2 px-6">
        <div class="flex items-center gap-2">
            <flux:icon.lock-closed variant="outline" class="size-4" />
            <flux:heading size="lg" level="3">{{ __('2FA recovery codes') }}</flux:heading>
        </div>

        <flux:text variant="subtle">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </flux:text>
    </div>

    <div class="space-y-4 px-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" size="sm" x-on:click="showRecoveryCodes = ! showRecoveryCodes">
                <span x-show="!showRecoveryCodes">{{ __('View recovery codes') }}</span>
                <span x-show="showRecoveryCodes" x-cloak>{{ __('Hide recovery codes') }}</span>
            </flux:button>

            <flux:button variant="ghost" size="sm" wire:click="regenerateRecoveryCodes" x-show="showRecoveryCodes" x-cloak>
                {{ __('Regenerate codes') }}
            </flux:button>
        </div>

        @error('recoveryCodes')
            <flux:callout variant="danger" icon="x-circle" :heading="$message" />
        @enderror

        <div x-show="showRecoveryCodes" x-cloak class="space-y-3">
            @if ($recoveryCodes === [])
                <flux:text>{{ __('No recovery codes available.') }}</flux:text>
            @else
                <div class="grid gap-1 rounded-lg border border-border-default bg-background-subtle p-4 font-mono text-sm text-text-primary sm:grid-cols-2">
                    @foreach ($recoveryCodes as $code)
                        <span>{{ $code }}</span>
                    @endforeach
                </div>

                <flux:text variant="subtle" class="text-xs">
                    {{ __('Each code can be used once. Regenerating replaces every code above.') }}
                </flux:text>
            @endif
        </div>
    </div>
</div>
