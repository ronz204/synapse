<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Contract rules R-01, R-02 and R-03 — the parts of "it feels instant" that are
 * structural rather than timed.
 *
 * These assert on the markup and the shipped assets rather than on a stopwatch.
 * That is on purpose: whether the sidebar survives navigation, and whether a
 * press is acknowledged without waiting for the server, are properties of the
 * code, not of the machine it runs on. The timing side lives in the browser
 * layer (php artisan perf:measure).
 */
it('keeps the sidebar mounted across navigation (R-03)', function (): void {
    $sidebar = File::get(base_path('resources/views/components/siga/sidebar.blade.php'));

    // The attribute form, not @persist: the directive wraps the aside in an
    // extra div which breaks the flex stretch, as the layout documents.
    expect($sidebar)->toContain('x-persist="sidebar"');
});

it('navigates without a full page reload (R-03)', function (): void {
    $sidebar = File::get(base_path('resources/views/components/siga/sidebar.blade.php'));

    $links = preg_match_all('/<a\s[^>]*href="\{\{\s*route\(/', $sidebar);
    $navigating = substr_count($sidebar, 'wire:navigate');

    // Every routed sidebar link must use wire:navigate. One that does not would
    // tear down and rebuild the whole shell, sidebar state included.
    expect($navigating)->toBeGreaterThanOrEqual($links);
});

it('acknowledges a press without waiting for the server (R-01)', function (): void {
    $script = File::get(base_path('resources/js/nav-feedback.js'));
    $entrypoint = File::get(base_path('resources/js/app.js'));

    expect($entrypoint)->toContain('nav-feedback.js')
        ->and($script)->toContain('pointerdown')
        ->and($script)->toContain('nav-pending')
        // It must also clean up, or an abandoned navigation leaves a link
        // stuck mid-press (R-06).
        ->and($script)->toContain('livewire:navigated');

    $css = File::get(base_path('resources/css/app.css'));

    expect($css)->toContain('.nav-item.nav-pending');
});

it('shows a skeleton shaped like the content, not a spinner (R-02)', function (): void {
    $skeleton = File::get(base_path('resources/views/components/ui/skeleton.blade.php'));
    $table = File::get(base_path('resources/views/components/ui/data-table.blade.php'));

    expect($skeleton)->toContain('--table-cols')
        ->and($table)->toContain('<x-ui.skeleton')
        // Delayed, so a fast response never flashes a placeholder.
        ->and($table)->toContain('wire:loading.delay')
        // Scoped, so an unrelated action never blanks the table.
        ->and($table)->toContain('wire:target');
});

it('does not animate the skeleton for users who asked for reduced motion', function (): void {
    $css = File::get(base_path('resources/css/app.css'));

    expect($css)->toContain('prefers-reduced-motion');
});
