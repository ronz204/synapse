<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Throwable;

/**
 * Runs the actual Browsershot/Chromium render off the HTTP request path
 * — see performance.md P1: a synchronous PDF export previously blocked a
 * full HTTP worker for 7-20s per download, and a handful of concurrent
 * exports could exhaust the whole worker pool. The Livewire action that
 * used to call PdfExporterInterface directly now dispatches this job
 * and returns immediately; App\Livewire\Concerns\InteractsWithExports
 * polls the Cache-backed status this job writes until it's ready.
 *
 * Rows arrive already mapped to their final exportable shape (label =>
 * formatted value) — that mapping is cheap, pure-PHP formatting with no
 * I/O, so it stays synchronous in the Livewire action; only the actual
 * Chromium render is worth pushing into the background. $headers here
 * carries only {key, label} (no `format` callback — a Closure isn't
 * serializable onto a queued job's payload, and by this point it's
 * already been applied). The download filename isn't part of this job
 * at all — it lives on the component ($pdfExportFilename) and is only
 * needed once InteractsWithExports::downloadQueuedPdf() actually streams
 * the file, never before.
 */
final class GenerateTableExportPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    /**
     * @param  array<int, array{key: string, label: string}>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly string $exportId,
        private readonly string $title,
        private readonly array $headers,
        private readonly array $rows,
        private readonly string $paperSize,
    ) {
        // Headroom over Browsershot's own timeout (config('exports.pdf.timeout'))
        // so the queue worker's timeout never fires first and masks the real
        // ProcessTimedOutException with a generic "job timed out" instead.
        $this->timeout = (int) config('exports.pdf.timeout') + 15;
    }

    public function handle(PdfExporterInterface $exporter): void
    {
        $html = view('exports.table-pdf', [
            'title' => $this->title,
            'headers' => $this->headers,
            'rows' => $this->rows,
        ])->render();

        $bytes = $exporter->toBytes($html, $this->paperSize);

        $path = 'exports/'.$this->exportId.'.pdf';
        Storage::disk('local')->put($path, $bytes);

        Cache::put(self::cacheKey($this->exportId), [
            'status' => 'ready',
            'path' => $path,
        ], now()->addMinutes(30));
    }

    /**
     * Laravel calls this automatically once the job exhausts its retries
     * (default: once) — surfaces as 'failed' to the polling component
     * instead of leaving it stuck on 'pending' forever.
     */
    public function failed(?Throwable $exception): void
    {
        Cache::put(self::cacheKey($this->exportId), [
            'status' => 'failed',
        ], now()->addMinutes(30));
    }

    public static function cacheKey(string $exportId): string
    {
        return "pdf-export:{$exportId}";
    }
}
