<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $email = '';

    #[Validate('nullable|image|max:2048')]
    public ?TemporaryUploadedFile $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Store the uploaded picture and point the user at it, replacing any
     * previous file so orphaned avatars don't pile up on disk.
     */
    public function updateAvatar(): void
    {
        $this->validateOnly('avatar');

        if (! $this->avatar instanceof TemporaryUploadedFile) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        $path = $this->avatar->store('avatars', 'public');

        // store() reports a write failure by returning false; without this the
        // column would be set to an empty string and the old file deleted.
        if ($path === false) {
            $this->addError('avatar', __('The picture could not be saved. Please try again.'));

            return;
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->avatar_path = $path;
        $user->save();

        $this->reset('avatar');

        Flux::toast(variant: 'success', text: __('Avatar updated.'));
    }
}
