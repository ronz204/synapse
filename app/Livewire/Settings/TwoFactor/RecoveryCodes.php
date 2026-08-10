<?php

declare(strict_types=1);

namespace App\Livewire\Settings\TwoFactor;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecoveryCodes extends Component
{
    #[Locked]
    public bool $requiresConfirmation = false;

    /** @var array<int, string> */
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Issue a fresh set of codes, invalidating the previous ones.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(Auth::user());

        $this->loadRecoveryCodes();
    }

    /**
     * Decrypt the stored codes for display.
     */
    private function loadRecoveryCodes(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasEnabledTwoFactorAuthentication() || ! $user->two_factor_recovery_codes) {
            return;
        }

        try {
            $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        } catch (Exception) {
            $this->addError('recoveryCodes', __('Failed to load recovery codes'));

            $this->recoveryCodes = [];
        }
    }
}
