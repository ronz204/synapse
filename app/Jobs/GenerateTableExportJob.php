<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Throwable;

/**
 * Builds an export file outside the HTTP request.
 *
 * Why this exists: SpatiePdfExporter boots a full headless Chromium through
 * Browsershot. That start-up costs seconds and does not depend on how many rows
 * are being exported, so no amount of query tuning touches it. SC-005 forbids
 * going past three seconds without an indicator and FR-012 asks for exactly
 * this shape — acknowledge immediately, deliver afterwards.
 *
 * The payload is deliberately plain arrays. Rows are mapped to their export
 * columns before dispatch, because the header definitions carry `format`
 * closures and a closure cannot be serialised into a queue payload.
 */
class GenerateTableExportJob implements ShouldQueue
{
    use Queueable;

    public const FORMAT_PDF = 'pdf';

    public const FORMAT_EXCEL = 'excel';

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** Long enough to survive a slow render, short enough not to litter. */
    public const STATUS_TTL_MINUTES = 60;

    /**
     * @param  array<int, array<string, mixed>>  $rows  Already mapped to export columns and keyed by label.
     */
    public function __construct(
        public readonly string $exportId,
        public readonly string $format,
        public readonly string $title,
        public readonly array $rows,
        public readonly string $filename,
        public readonly string $paperSize = 'a4',
    ) {}

    public static function cacheKey(string $exportId): string
    {
        return "export:{$exportId}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function status(string $exportId): ?array
    {
        return Cache::get(self::cacheKey($exportId));
    }

    public function handle(PdfExporterInterface $pdf, ExcelExporterInterface $excel): void
    {
        try {
            $path = "exports/{$this->exportId}-{$this->filename}";

            $response = $this->format === self::FORMAT_PDF
                ? $pdf->fromHtml($this->renderHtml(), $this->filename, $this->paperSize)
                : $excel->streamDownload($this->rows, $this->filename);

            // Both ports are designed to hand a download straight to the
            // browser, which is the right shape for their normal caller. Here
            // the bytes have to land on disk instead, and capturing the stream
            // is what lets that happen without widening the port contract —
            // which would need agreement from the other side of the boundary
            // for no gain (Principle V).
            ob_start();
            $response->sendContent();
            $bytes = (string) ob_get_clean();

            Storage::disk('local')->put($path, $bytes);

            Cache::put(self::cacheKey($this->exportId), [
                'status' => self::STATUS_READY,
                'path' => $path,
                'filename' => $this->filename,
            ], now()->addMinutes(self::STATUS_TTL_MINUTES));
        } catch (Throwable $e) {
            // Recorded rather than swallowed: without this the UI would poll a
            // key that never resolves, which is precisely the indefinite
            // spinner FR-009 rules out.
            Cache::put(self::cacheKey($this->exportId), [
                'status' => self::STATUS_FAILED,
                'message' => $e->getMessage(),
            ], now()->addMinutes(self::STATUS_TTL_MINUTES));

            throw $e;
        }
    }

    private function renderHtml(): string
    {
        return view('exports.table-pdf', [
            'title' => $this->title,
            'headers' => array_map(
                static fn (string $label): array => ['key' => $label, 'label' => $label],
                array_keys($this->rows[0] ?? []),
            ),
            'rows' => $this->rows,
        ])->render();
    }
}
