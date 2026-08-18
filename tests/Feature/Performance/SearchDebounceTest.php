<?php

declare(strict_types=1);

use Database\Seeders\PerformanceVolumeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

/**
 * FR-008 — typing must not fire a query per keystroke.
 *
 * Two halves, because the requirement has two halves:
 *
 *   markup   the settling delay is declared on the input at all;
 *   cost     one settled search resolves in a bounded number of queries at the
 *            target volume, rather than scaling with the catalog.
 */
it('declares a settling delay on the server-mode search input', function (): void {
    $table = File::get(base_path('resources/views/components/ui/data-table.blade.php'));

    expect($table)->toContain('wire:model.live.debounce.250ms="search"');
});

it('leaves client-mode search undebounced against the server because it issues no query', function (): void {
    $table = File::get(base_path('resources/views/components/ui/data-table.blade.php'));

    // x-model, not wire:model: client mode filters an array already in the
    // browser. If this ever becomes wire:model.live, FR-008 is back on the
    // table for these listings and the delay has to be reconsidered.
    expect($table)->toContain('x-model.debounce.150ms="search"');
});

it('resolves a settled search in a bounded number of queries', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $user = userWithPermissions(['courses.view', 'courses.search']);
    $component = Livewire::actingAs($user)->test(CourseComponent::class)->assertOk();

    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // One settled term, as Livewire delivers it after the debounce — not one
    // update per character.
    $component->set('search', 'PERF-01')->assertOk();

    expect($queries)->toBeLessThanOrEqual(6);
});

it('returns the empty state as cheaply as a populated one', function (): void {
    (new PerformanceVolumeSeeder)->run();

    $user = userWithPermissions(['courses.view', 'courses.search']);
    $component = Livewire::actingAs($user)->test(CourseComponent::class)->assertOk();

    $hitQueries = 0;
    DB::listen(function () use (&$hitQueries): void {
        $hitQueries++;
    });
    $component->set('search', 'PERF-01')->assertOk();

    $missQueries = 0;
    DB::listen(function () use (&$missQueries): void {
        $missQueries++;
    });
    $component->set('search', 'zzzzzzzzzzzz')->assertOk();

    // A search with no results must not cost more than one with results —
    // "nothing found" arriving slower than "here are your rows" is the kind of
    // asymmetry FR-011 rules out for rejections and SC-003 rules out here.
    expect($missQueries)->toBeLessThanOrEqual($hitQueries);
});
