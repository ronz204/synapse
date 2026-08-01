---
paths:
  - "app/Livewire/**/*.php"
  - "resources/views/livewire/**/*.blade.php"
  - "resources/views/components/⚡*.blade.php"
---

# Livewire Practices for This Project

Complements the `livewire-development` skill (component-format decision process, Livewire 4 feature/directive reference, `wire:key`/`wire:loading`/testing basics — already covered there, not repeated here). This file states the conventions already established in this codebase and the Hexagonal/DDD boundary Livewire components sit behind.

## This Project Uses Class-Based Components, Not v4's SFC Default

Livewire 4 defaults to single-file components (SFC) under `resources/views/components/⚡*.blade.php`. This project doesn't use that — every existing component is class-based (Livewire 3 style): `app/Livewire/Settings/Profile.php` paired with `resources/views/livewire/settings/profile.blade.php`, same for `Security`, `Appearance`, `DeleteUserForm`. Follow this same shape for new components:

```
app/Livewire/<Namespace>/<ComponentName>.php
resources/views/livewire/<namespace>/<kebab-component-name>.blade.php
```

`php artisan make:livewire <Namespace>/<Name> --class` produces this layout. Don't introduce SFC/MFC components alongside it — a mixed format across a small component set makes every new file a "which style is this" question with no payoff.

## `resources/views/livewire/auth/*.blade.php` Are Not Livewire Components

`login.blade.php`, `register.blade.php`, `forgot-password.blade.php`, and `reset-password.blade.php` live under a `livewire` directory but are plain Blade views with no backing component class. They're wired through Fortify directly:

```php
// app/Providers/FortifyServiceProvider.php
Fortify::loginView(fn () => view('livewire.auth.login'));
```

They submit to Fortify's own routes (`<form method="POST" action="{{ route('login.store') }}">`), not `wire:submit`. Don't add `wire:model`, `wire:submit`, or expect a component class to back these views — Fortify owns that request/response cycle end to end. If an auth screen ever needs Livewire reactivity, that's a deliberate move to a Fortify custom-response contract plus a real component, not an incremental edit to the existing Blade view.

## Delegate Side Effects to Invokable Actions, Not Inline Component Logic

`DeleteUserForm` doesn't inline its logout logic — it injects the existing `App\Livewire\Actions\Logout` action into the method itself and calls it:

```php
public function deleteUser(Logout $logout): void
{
    $this->validate(['password' => $this->currentPasswordRules()]);

    tap(Auth::user(), $logout(...))->delete();

    $this->redirect('/', navigate: true);
}
```

Keep this pattern for anything beyond trivial state mutation, and especially once the curricular domain lands: a Livewire action method calling `EquivalencyRepository`/domain services to run cycle or contradiction checks, not embedding that logic in the component class. Per this project's Hexagonal/DDD constraint, a Livewire component is an adapter — it collects input, calls into the domain (directly or via an Action), and renders the result. It never contains the business rule itself.

## Inject Dependencies via Method Parameters, Not the Constructor

Livewire components are rehydrated from serialized public state on every request — constructor injection doesn't behave the way it does in a normal Laravel class. Use Laravel's automatic method injection on `mount()` and action methods instead, as `Security` and `DeleteUserForm` already do:

```php
public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void {}

public function deleteUser(Logout $logout): void { /* ... */ }
```

Avoid `app()`/`resolve()` calls inside a component for the same reason constructor injection is avoided elsewhere in this codebase (see `architecture.md` in the `laravel-best-practices` skill) — method injection gets you the same testability without fighting Livewire's lifecycle.

## Reuse Validation Rules via `Concerns` Traits

`PasswordValidationRules` and `ProfileValidationRules` (`app/Concerns/`) hold rule sets shared across more than one component — `Security` and `DeleteUserForm` both use `PasswordValidationRules` rather than each redefining the same password rule array. When a validation rule set is needed by more than one component, or by a component and a Fortify action, extract it into `app/Concerns/` instead of duplicating the rule array inline.

## Reset Sensitive State After Validation, Success or Failure

A Livewire component's public properties are part of its serialized, client-visible state — a password left in `$this->password` after a failed or successful submission stays there until explicitly cleared. `Security::updatePassword()` resets on both paths:

```php
try {
    $validated = $this->validate([...]);
} catch (ValidationException $e) {
    $this->reset('current_password', 'password', 'password_confirmation');
    throw $e;
}

// ... persist ...

$this->reset('current_password', 'password', 'password_confirmation');
```

Apply the same reset-on-both-paths pattern to any component holding a password, token, or other sensitive value.

## Use `Flux::toast()` for User Feedback

Every existing component reports success via `Flux::toast(variant: 'success', text: __('...'))`, not a manual session-flash banner. Flux is a first-party dependency (`livewire/flux`) — use its toast API for confirmation/error feedback rather than introducing a second notification mechanism.

## `wire:key` at Every Nesting Level for Hierarchical UI

The `livewire-development` skill already requires `wire:key` in loops generally; this project has a specific case worth calling out ahead of time. RC-01's study-plan structure is a three-level nested loop (levels → courses → prerequisites within each course). Every nesting level needs its own `wire:key`, not just the innermost `@foreach` — Livewire's DOM diffing can misattribute state across sibling levels if one nested list reorders (e.g. a level's courses get reordered) while the outer list's keys stay generic.

```blade
@foreach ($levels as $level)
    <div wire:key="level-{{ $level->id }}">
        @foreach ($level->courses as $course)
            <div wire:key="course-{{ $course->id }}">
                @foreach ($course->prerequisites as $prerequisite)
                    <div wire:key="prerequisite-{{ $prerequisite->id }}">...</div>
                @endforeach
            </div>
        @endforeach
    </div>
@endforeach
```
