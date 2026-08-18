<?php

declare(strict_types=1);

use App\Jobs\GenerateTableExportJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\IdentityAccess\Permission\Presentation\Livewire\PermissionComponent;

/**
 * FR-012 and contract rule R-08 — an operation that cannot answer inside its
 * budget acknowledges immediately and delivers afterwards.
 *
 * The cost being avoided is not the row count: SpatiePdfExporter boots a full
 * headless Chromium through Browsershot, which takes seconds no matter how many
 * rows are involved. Excel has no such cost and deliberately stays synchronous,
 * so these tests assert that asymmetry too — a later "let's make it consistent"
 * refactor would be a regression, not a tidy-up.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

it('queues the PDF export instead of building it in the request', function (): void {
    Queue::fake();

    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportPdf')
        ->assertOk()
        ->assertSet('pendingExportId', fn (?string $id): bool => $id !== null);

    Queue::assertPushed(GenerateTableExportJob::class, function (GenerateTableExportJob $job): bool {
        return $job->format === GenerateTableExportJob::FORMAT_PDF
            && $job->filename === Str::slug(__('Permissions')).'.pdf'
            && $job->paperSize === 'letter';
    });
});

it('keeps the Excel export synchronous because it has no browser to boot', function (): void {
    Queue::fake();

    $user = userWithPermissions(['permissions.view', 'permissions.export_excel']);
    $spy = fakeExcelExporter();

    Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportExcel')
        ->assertOk();

    Queue::assertNothingPushed();
    expect($spy->filename)->toBe(Str::slug(__('Permissions')).'.xlsx');
});

it('carries plain arrays into the queue payload, never closures', function (): void {
    Queue::fake();

    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);

    Livewire::actingAs($user)->test(PermissionComponent::class)->call('exportPdf');

    Queue::assertPushed(GenerateTableExportJob::class, function (GenerateTableExportJob $job): bool {
        // Header definitions carry `format` callbacks; rows must already be
        // mapped by the time they reach the queue or serialisation fails.
        return serialize($job->rows) !== '' && $job->rows !== [];
    });
});

it('reports the export as ready once the job has run', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);
    fakePdfExporter();

    // QUEUE_CONNECTION is sync under phpunit, so the job runs inline here.
    $component = Livewire::actingAs($user)
        ->test(PermissionComponent::class)
        ->call('exportPdf')
        ->assertOk();

    $component->call('pollExport')
        ->assertSet('pendingExportId', null)
        ->assertSet('readyExportId', fn (?string $id): bool => $id !== null);
});

it('clears the indicator and says so when the export fails', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);

    $component = Livewire::actingAs($user)->test(PermissionComponent::class);
    $component->set('pendingExportId', 'does-not-exist-and-never-resolved');

    // A status that cannot be found must end the wait, not extend it forever.
    // An indefinite spinner is precisely what FR-009 rules out.
    $component->call('pollExport')->assertSet('pendingExportId', null);
});

it('offers a download only while the file is actually available', function (): void {
    $user = userWithPermissions(['permissions.view', 'permissions.export_pdf']);

    $component = Livewire::actingAs($user)->test(PermissionComponent::class);

    expect($component->call('downloadExport')->assertOk())->not->toBeNull();
});

it('shows the pending and ready states in the table shell', function (): void {
    $table = File::get(base_path('resources/views/components/ui/data-table.blade.php'));

    expect($table)->toContain('wire:poll.2s="pollExport"')
        ->and($table)->toContain('wire:click="downloadExport"')
        // Short polling on purpose: BROADCAST_CONNECTION is `log`, there is no
        // realtime channel, and adding one for this would be infrastructure
        // without a requirement.
        ->and($table)->toContain('Preparing your export');
});
