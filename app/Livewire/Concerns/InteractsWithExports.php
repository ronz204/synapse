<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Infrastructure\Jobs\GenerateTableExportPdfJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns the export ports (Src\Shared\Export\Contracts\*) into two
 * ready-to-call helpers any CRUD's Livewire component can use, without
 * re-writing the "map rows -> call exporter" plumbing each time.
 *
 * What stays generic here (write once): projecting rows down to
 * exportable columns, rendering the PDF table shell, calling the ports.
 *
 * What each component still defines (the "adapts to each table" part):
 * its own column set, in `{key, label}` pairs — the same shape already
 * used for <x-ui.data-table>'s headers prop, so export columns and
 * on-screen columns share one source of truth — plus an optional
 * `format` callback per column when the raw stored value isn't what
 * should land in the file (e.g. Role's `protected` boolean becoming
 * "Sistema"/"Personalizado" instead of "1"/"").
 *
 * Authorization is intentionally NOT handled here — call
 * $this->authorize(...) in your own exportExcel()/exportPdf() before
 * calling these helpers, exactly like every other action in this app.
 *
 * Excel stays synchronous (streamExcel(): SpatieExcelExporter already
 * streams row-by-row with constant memory, so there's nothing to gain
 * by queuing it — see performance.md, "Qué NO hacer"). PDF does not:
 * Browsershot launches a full headless Chromium process per export
 * (7-20s), which blocks an HTTP worker for that whole time and can
 * exhaust the worker pool under a handful of concurrent exports — see
 * performance.md P1. queuePdfExport() below dispatches that render to
 * GenerateTableExportPdfJob and returns immediately; checkPdfExportStatus()/
 * downloadQueuedPdf() are what the UI (<x-ui.pdf-export-status>) polls
 * and calls once the job is done.
 *
 * Usage (see RoleComponent for the full example):
 *
 *   public function exportExcel(ExcelExporterInterface $exporter, ListRolesUseCase $useCase): StreamedResponse
 *   {
 *       $this->authorize('exportExcel', Role::class);
 *
 *       return $this->streamExcel($this->exportHeaders(), $this->freshRows($useCase), 'roles.xlsx', $exporter);
 *   }
 *
 *   public function exportPdf(ListRolesUseCase $useCase): void
 *   {
 *       $this->authorize('exportPdf', Role::class);
 *
 *       $this->queuePdfExport(__('Roles'), $this->exportHeaders(), $this->freshRows($useCase), 'roles.pdf');
 *   }
 *
 * Scaling Excel to a genuinely large table: streamExcel() accepts any
 * `iterable`, on purpose — the whole export pipeline (this trait, the
 * port, the adapter) is already constant-memory-capable for a table with
 * millions of rows, with zero changes needed here. What has to change is
 * what YOUR component feeds it: instead of a method like Role/Permission's
 * `freshRows()` that calls the repository's `all()` (which does `->get()`
 * — loads every row into a PHP array up front, correct for a small
 * catalog, wrong for a huge one), a large-table repository should expose
 * a method backed by Eloquent's `cursor()`:
 *
 *   public function allForExport(): iterable
 *   {
 *       foreach (DocenteModel::query()->cursor() as $model) {
 *           yield $this->toDomain($model);
 *       }
 *   }
 *
 * One real trade-off to know about before reaching for cursor(): it
 * does not play well with eager-loaded relations (`->with(...)`) —
 * Eloquent can't build the relation's single `WHERE IN (...)` query
 * without first knowing every parent id, which conflicts with fetching
 * one row at a time. That's exactly why Role's own repository keeps
 * using `->with('permissions')->get()` instead of `cursor()` — it's a
 * handful of rows, and switching would have reintroduced the N+1 query
 * problem this app already fixed once. Reach for `cursor()` when a
 * table is genuinely large AND doesn't need an eager-loaded relation
 * for its export columns; keep `get()` when it does. PDF's own rows are
 * always fully materialized before queuePdfExport() is called (the whole
 * report is one HTML string either way), so this trade-off only applies
 * to Excel.
 */
trait InteractsWithExports
{
    /**
     * Non-null while a PDF export triggered by this component is
     * pending/ready/failed — null the rest of the time. Drives
     * <x-ui.pdf-export-status>'s wire:poll and its download/dismiss
     * actions; see checkPdfExportStatus()/downloadQueuedPdf() below.
     */
    public ?string $pdfExportId = null;

    /**
     * 'pending' | 'ready' | 'failed'. Mirrors the Cache entry
     * GenerateTableExportPdfJob writes, refreshed by checkPdfExportStatus()
     * on each poll rather than read directly in the view, so the view
     * never has to know the Cache key format.
     */
    public ?string $pdfExportStatus = null;

    public ?string $pdfExportFilename = null;

    /**
     * Epoch milliseconds when the current export was dispatched. Lets
     * checkPdfExportStatus() enforce a minimum visible "pending" stretch —
     * without it, a fast render (a small catalog, an already-warm
     * Browsershot/Chromium process) can finish before the user's eye ever
     * registers the spinner, which reads as "the loading state is broken"
     * even though it isn't.
     */
    public ?int $pdfExportStartedAtMs = null;

    /**
     * Excel has no pending phase to show a toast for — streamDownload()
     * generates and streams the file synchronously in this same request
     * (see the class docblock for why) — but the user still deserves the
     * same "something happened" confirmation the queued PDF flow gives via
     * <x-ui.pdf-export-status>. Reuses this app's existing toast
     * convention (Flux::toast(), bridged in resources/js/toast.js) instead
     * of introducing a second notification mechanism just for exports.
     *
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function streamExcel(array $headers, iterable $rows, string $filename, ExcelExporterInterface $exporter): StreamedResponse
    {
        $response = $exporter->streamDownload($this->mapRowsForExport($headers, $rows), $filename);

        $this->dispatch('toast', variant: 'success', text: __('Your Excel export is ready to download.'));

        return $response;
    }

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     * @param  string  $paperSize  Passed straight through to the queued job —
     *                             see PdfExporterInterface for why it exists.
     */
    protected function queuePdfExport(string $title, array $headers, iterable $rows, string $filename, string $paperSize = 'a4'): void
    {
        $exportId = (string) Str::uuid();

        Cache::put(GenerateTableExportPdfJob::cacheKey($exportId), ['status' => 'pending'], now()->addMinutes(30));

        GenerateTableExportPdfJob::dispatch(
            $exportId,
            $title,
            array_map(
                fn (array $header): array => ['key' => $header['key'], 'label' => $header['label']],
                $headers,
            ),
            iterator_to_array($this->mapRowsForExport($headers, $rows)),
            $paperSize,
        );

        $this->pdfExportId = $exportId;
        $this->pdfExportStatus = 'pending';
        $this->pdfExportFilename = $filename;
        $this->pdfExportStartedAtMs = (int) (microtime(true) * 1000);
    }

    /**
     * Polled by <x-ui.pdf-export-status>'s wire:poll while $pdfExportId
     * is set — a single Cache read, never a query. A missing Cache entry
     * (TTL expired, or Cache flushed) reads the same as a failure: there
     * is nothing left to download either way.
     *
     * Holds the reveal of a ready/failed result until a minimum stretch of
     * wall-clock time has passed since the export was dispatched —
     * otherwise a render that finishes before that (a small catalog, a
     * warm Browsershot process) flips straight to "ready" before the
     * spinner has been on screen long enough to register as loading at
     * all. The next poll tick always clears this gate, so this never
     * delays the real result by more than one extra poll. Skipped under
     * the test suite, where QUEUE_CONNECTION=sync means the job has
     * already finished by the time this is first called and there is no
     * real wall-clock wait to protect.
     */
    public function checkPdfExportStatus(): void
    {
        if ($this->pdfExportId === null) {
            return;
        }

        $state = Cache::get(GenerateTableExportPdfJob::cacheKey($this->pdfExportId));
        $status = $state['status'] ?? 'failed';

        $minPendingVisibleMs = 1200;
        $elapsedMs = (int) (microtime(true) * 1000) - ($this->pdfExportStartedAtMs ?? 0);

        if (! app()->runningUnitTests() && $status !== 'pending' && $elapsedMs < $minPendingVisibleMs) {
            return;
        }

        $this->pdfExportStatus = $status;
    }

    public function downloadQueuedPdf(): StreamedResponse
    {
        abort_if($this->pdfExportId === null, 404);

        $state = Cache::get(GenerateTableExportPdfJob::cacheKey($this->pdfExportId));

        abort_if(($state['status'] ?? null) !== 'ready', 404);

        $path = $state['path'];
        $filename = $this->pdfExportFilename ?? 'export.pdf';

        Cache::forget(GenerateTableExportPdfJob::cacheKey($this->pdfExportId));
        $this->pdfExportId = null;
        $this->pdfExportStatus = null;
        $this->pdfExportFilename = null;
        $this->pdfExportStartedAtMs = null;

        // The file on disk is cleaned up by the `exports:prune` scheduled
        // command, not here: FilesystemAdapter::download() returns a
        // StreamedResponse whose callback reads the file lazily once
        // Symfony actually sends the response (after this method already
        // returned) — deleting it here would race that callback.
        return Storage::disk('local')->download($path, $filename);
    }

    /**
     * Lets the user dismiss a failed (or no-longer-wanted) export notice
     * without downloading anything.
     */
    public function dismissPdfExportNotice(): void
    {
        if ($this->pdfExportId !== null) {
            Cache::forget(GenerateTableExportPdfJob::cacheKey($this->pdfExportId));
        }

        $this->pdfExportId = null;
        $this->pdfExportStatus = null;
        $this->pdfExportFilename = null;
        $this->pdfExportStartedAtMs = null;
    }

    /**
     * Projects each row down to just the exportable columns, keyed by
     * each header's *label* (so the spreadsheet/PDF header row reads
     * "Nombre", not "name"), applying that column's `format` callback
     * when one is given. A generator on purpose — this must stay lazy
     * so a caller that supplies a genuinely lazy $rows source (a DB
     * cursor, for a future large table) keeps the Excel exporter's
     * constant-memory guarantee end to end. queuePdfExport() forces this
     * eager immediately (iterator_to_array()) since a queued job's
     * payload can't carry a live generator across process boundaries —
     * that's fine, the whole PDF is one HTML string either way.
     *
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     * @return \Generator<int, array<string, mixed>>
     */
    private function mapRowsForExport(array $headers, iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            $mapped = [];

            foreach ($headers as $header) {
                // A `format` callback must see the raw value, null included —
                // a column's own callback (e.g. StudyPlan's enrollment closing
                // date, `fn (?string $v) => $v ?? '—'`) is precisely what
                // decides how a missing value should render. Collapsing null
                // to '' here would run before that decision and make such a
                // callback's null branch permanently unreachable.
                $value = $row[$header['key']] ?? null;
                $mapped[$header['label']] = isset($header['format']) ? ($header['format'])($value) : ($value ?? '');
            }

            yield $mapped;
        }
    }
}
