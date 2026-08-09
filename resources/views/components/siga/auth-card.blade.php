{{--
    Landing-page sign-in card. Shows the institutional form to guests and a way
    back into the dashboard to anyone already signed in. The chrome and the form
    are shared with the standalone /login screen, so both stay identical.
--}}
<section>
    <x-siga.auth-panel class="lg:mb-0 mb-10">
        @auth
            <div class="text-center">
                <h2 class="text-xl font-bold text-text-primary mb-1">{{ __('Welcome back!') }}</h2>
                <p class="text-[13.5px] text-text-secondary mb-6">{{ auth()->user()->name }}</p>

                <a href="{{ route('dashboard') }}" wire:navigate class="block w-full bg-background-brand-default hover:bg-background-brand-hover text-text-inverse text-[15px] font-bold py-3.5 rounded-lg transition-colors">
                    {{ __('Go to Dashboard') }}
                </a>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full text-[13px] font-semibold text-text-tertiary hover:text-text-brand transition-colors">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        @else
            <h2 class="text-xl font-bold text-text-primary mb-1 text-center">{{ __('Log in') }}</h2>
            <p class="text-[13.5px] text-text-secondary mb-6 text-center">{{ __('UTN Institutional Account') }}</p>

            <x-siga.login-form />
        @endauth
    </x-siga.auth-panel>
</section>
