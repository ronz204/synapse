<x-layouts::auth.siga :title="__('Two-factor authentication')">
    <div
        x-cloak
        x-data="{
            showRecoveryInput: @js($errors->has('recovery_code')),
            code: '',
            recovery_code: '',
            focusOtp() {
                this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
            },
            init() {
                if (! this.showRecoveryInput) {
                    this.focusOtp();
                }
            },
            toggleInput() {
                this.showRecoveryInput = ! this.showRecoveryInput;

                this.code = '';
                this.recovery_code = '';

                this.$nextTick(() => {
                    this.showRecoveryInput
                        ? this.$refs.recovery_code?.focus()
                        : this.focusOtp();
                });
            },
        }">

        <div x-show="!showRecoveryInput">
            <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Authentication code') }}</h2>
            <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('Enter the authentication code provided by your authenticator application.') }}</p>
        </div>

        <div x-show="showRecoveryInput">
            <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Recovery code') }}</h2>
            <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}</p>
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}">
            @csrf

            <div class="space-y-5 text-center">
                <div x-show="!showRecoveryInput">
                    <div class="flex items-center justify-center my-5" x-ref="otp">
                        <flux:otp x-model="code" length="6" name="code" :label="__('Authentication code')" label:sr-only class="mx-auto" />
                    </div>
                </div>

                <div x-show="showRecoveryInput">
                    <div class="my-5">
                        <input
                            type="text"
                            name="recovery_code"
                            x-ref="recovery_code"
                            x-bind:required="showRecoveryInput"
                            x-model="recovery_code"
                            autocomplete="one-time-code"
                            aria-label="{{ __('Recovery code') }}"
                            class="w-full px-3.5 py-3 border border-border-strong rounded-lg text-[14.5px] text-text-primary bg-background-surface outline-none focus:border-border-focus focus:ring-3 focus:ring-border-focus/10 transition-all">
                    </div>

                    @error('recovery_code')
                        <p class="text-[13px] text-status-danger">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full bg-background-brand-default hover:bg-background-brand-hover text-text-inverse py-3 rounded-lg text-[15px] font-bold transition-colors">
                    {{ __('Continue') }}
                </button>
            </div>

            <p class="mt-5 text-center text-[13px] text-text-secondary">
                {{ __('or you can') }}
                <button type="button" class="font-semibold text-text-brand underline hover:text-background-brand-hover transition-colors" @click="toggleInput()">
                    <span x-show="!showRecoveryInput">{{ __('login using a recovery code') }}</span>
                    <span x-show="showRecoveryInput">{{ __('login using an authentication code') }}</span>
                </button>
            </p>
        </form>
    </div>
</x-layouts::auth.siga>
