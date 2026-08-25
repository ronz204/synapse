<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Modality;
use App\Models\ModalityResolution;
use Livewire\Livewire;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityComponent;

it('blocks mounting the component for a user without modalities.view', function (): void {
    $user = userWithPermissions([]);

    Livewire::actingAs($user)->test(ModalityComponent::class)->assertForbidden();
});

it('blocks creating a modality for a user without modalities.create', function (): void {
    $user = userWithPermissions(['modalities.view']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->set('form.name', 'Semi-presencial')
        ->call('save')
        ->assertForbidden();
});

it('creates a modality for a user holding modalities.create', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.create']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->set('form.name', 'Semi-presencial')
        ->set('form.requiresResolution', true)
        ->call('save')
        ->assertOk()
        ->assertHasNoErrors();

    $modality = Modality::query()->where('name', 'Semi-presencial')->first();
    expect($modality)->not->toBeNull();
    expect($modality->requires_resolution)->toBeTrue();
});

it('blocks a modality name containing characters outside the allowed name pattern', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.create']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->set('form.name', 'Virtual <script>#!')
        ->call('save')
        ->assertHasErrors(['form.name']);

    expect(Modality::query()->where('name', 'Virtual <script>#!')->exists())->toBeFalse();
});

it('blocks a duplicate modality name', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.create']);
    Modality::factory()->create(['name' => 'Virtual']);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->set('form.name', 'Virtual')
        ->call('save')
        ->assertHasErrors(['form.name']);
});

it('edits an existing modality for a user holding modalities.edit', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.edit']);
    $modality = Modality::factory()->create(['name' => 'Virtual', 'requires_resolution' => false]);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('openEditModal', $modality->id)
        ->set('form.requiresResolution', true)
        ->call('save')
        ->assertOk();

    expect($modality->fresh()->requires_resolution)->toBeTrue();
});

it('blocks deleting a modality for a user without modalities.delete', function (): void {
    $user = userWithPermissions(['modalities.view']);
    $modality = Modality::factory()->create();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('delete', $modality->id)
        ->assertForbidden();
});

it('deletes a modality not referenced by any course or resolution', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.delete']);
    $modality = Modality::factory()->create();

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('delete', $modality->id)
        ->assertOk();

    expect(Modality::query()->whereKey($modality->id)->exists())->toBeFalse();
});

it('rejects deleting a modality still referenced by a course with a specific message, not a raw database error', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.delete']);
    $modality = Modality::factory()->create();
    Course::factory()->create(['modality_id' => $modality->id]);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('delete', $modality->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect(Modality::query()->whereKey($modality->id)->exists())->toBeTrue();
});

it('rejects deleting a modality still referenced by a historical resolution, not just a course', function (): void {
    $user = userWithPermissions(['modalities.view', 'modalities.delete']);
    $modality = Modality::factory()->requiresResolution()->create();
    ModalityResolution::factory()->create(['modality_id' => $modality->id]);

    Livewire::actingAs($user)
        ->test(ModalityComponent::class)
        ->call('delete', $modality->id)
        ->assertDispatched('toast-show', dataset: ['variant' => 'danger']);

    expect(Modality::query()->whereKey($modality->id)->exists())->toBeTrue();
});
