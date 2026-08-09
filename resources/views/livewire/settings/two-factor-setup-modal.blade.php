<flux:modal name="two-factor-setup-modal" class="max-w-md md:min-w-md" @close="closeModal">
    <div class="space-y-6">
        <div class="flex flex-col items-center space-y-4">
            <div class="flex size-12 items-center justify-center rounded-full bg-background-brand-subtle text-text-brand">
                <flux:icon.qr-code />
            </div>

            <div class="space-y-2 text-center">
                <flux:heading size="lg">{{ $this->modalConfig['title'] }}</flux:heading>
                <flux:text>{{ $this->modalConfig['description'] }}</flux:text>
            </div>
        </div>

        @if ($showVerificationStep)
            <div class="space-y-6">
                <div class="flex flex-col items-center justify-center space-y-3" x-data x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                    <flux:otp name="code" wire:model="code" length="6" :label="__('Authentication code')" label:sr-only class="mx-auto" />
                </div>

                <div class="flex items-center space-x-3">
                    <flux:button variant="outline" class="flex-1" wire:click="resetVerification">{{ __('Back') }}</flux:button>
                    <flux:button variant="primary" class="flex-1" wire:click="confirmTwoFactor" x-bind:disabled="$wire.code.length < 6">
                        {{ __('Confirm') }}
                    </flux:button>
                </div>
            </div>
        @else
            @error('setupData')
                <flux:callout variant="danger" icon="x-circle" :heading="$message" />
            @enderror

            <div class="flex justify-center">
                <div class="relative aspect-square w-64 overflow-hidden rounded-lg border border-border-default">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex animate-pulse items-center justify-center bg-background-subtle">
                            <flux:icon.loading />
                        </div>
                    @else
                        {{-- The QR is rendered on a white plate in both themes: inverting it
                             or letting a dark surface show through breaks scanning. --}}
                        <div class="flex h-full items-center justify-center p-4">
                            <div class="rounded bg-neutral-0 p-3">
                                {!! $qrCodeSvg !!}
                            </div>
                        </div>
                    @endempty
                </div>
            </div>

            <flux:button :disabled="$errors->has('setupData')" variant="primary" class="w-full" wire:click="showVerificationIfNecessary">
                {{ $this->modalConfig['buttonText'] }}
            </flux:button>

            <div class="space-y-4">
                <div class="relative flex w-full items-center justify-center">
                    <div class="absolute inset-0 top-1/2 h-px w-full bg-border-default"></div>
                    <span class="relative bg-background-surface px-2 text-sm text-text-tertiary">
                        {{ __('or, enter the code manually') }}
                    </span>
                </div>

                <div class="flex items-center space-x-2" x-data="{
                    copied: false,
                    async copy() {
                        await navigator.clipboard.writeText(@js($manualSetupKey));
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1500);
                    },
                }">
                    <div class="flex-1 overflow-x-auto rounded-lg border border-border-default bg-background-subtle px-3 py-2 font-mono text-sm text-text-primary">
                        {{ $manualSetupKey }}
                    </div>

                    <flux:button variant="ghost" size="sm" x-on:click="copy()" :aria-label="__('Copy setup key')">
                        <span x-show="!copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</flux:modal>
