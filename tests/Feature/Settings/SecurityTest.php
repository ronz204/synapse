<?php

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * The security screen sits behind the password.confirm middleware, so a session
 * that has not confirmed recently is redirected away. These tests are about what
 * the screen renders, not about the gate, so they start already confirmed.
 */
beforeEach(function () {
    $this->withSession(['auth.password_confirmed_at' => time()]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertOk();
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    // Asserted through __() rather than as literals: the application runs in
    // Spanish, so hard-coded English would pass the assertDontSee checks for the
    // wrong reason — the strings would be absent because nothing is in English.
    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee(__('Update password'))
        ->assertDontSee(__('Manage your passkeys for passwordless sign-in'))
        ->assertDontSee(__('Add a passkey to sign in without a password'))
        ->assertDontSee(__('Two-factor authentication'));
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});
