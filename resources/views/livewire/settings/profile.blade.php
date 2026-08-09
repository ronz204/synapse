<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <div class="mb-6 flex items-center gap-4">
            @if (auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="h-16 w-16 rounded-full object-cover">
            @else
                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="lg" />
            @endif

            <div>
                <input type="file" wire:model="avatar" accept="image/*" id="avatar-input" class="hidden">

                <div class="flex items-center gap-2">
                    <label for="avatar-input" class="cursor-pointer">
                        <flux:button as="span" variant="ghost" size="sm">{{ __('Change photo') }}</flux:button>
                    </label>

                    @if ($avatar)
                        <flux:button variant="primary" size="sm" wire:click="updateAvatar" wire:loading.attr="disabled" wire:target="updateAvatar">
                            {{ __('Upload') }}
                        </flux:button>
                    @endif
                </div>

                <div wire:loading wire:target="avatar" class="mt-1 text-sm text-text-tertiary">{{ __('Uploading...') }}</div>

                @error('avatar')
                    <flux:text class="mt-1 text-sm text-status-danger">{{ $message }}</flux:text>
                @enderror
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
