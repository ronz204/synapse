<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Src\Shared\Export\Contracts\PdfExporterInterface;

/**
 * Renders HTML to PDF via spatie/laravel-pdf, which delegates the
 * actual rendering to headless Chromium through Browsershot — the heavy
 * CSS/Tailwind layout work happens in the browser engine, never in the
 * PHP process itself. Always called from a queued job
 * (GenerateTableExportPdfJob), never from an HTTP request directly —
 * see PdfExporterInterface's own docblock for why.
 *
 * Uses ->generatePdfContent() (raw bytes) rather than the package's own
 * ->name()/Responsable path deliberately: that path has a documented,
 * open issue specifically inside Livewire ("Livewire needs pdf as a
 * string not base64" — spatie/laravel-pdf discussion #120), where the
 * PDF arrives base64-encoded instead of as a clean binary stream.
 * Generating the bytes ourselves keeps this adapter symmetric with
 * SpatieExcelExporter and gives the caller (the queued job) a plain
 * string to write to storage.
 *
 * ->withBrowsershot()->setNodeModulePath() points Browsershot's child
 * Node process straight at the project's own node_modules explicitly,
 * rather than relying on Node's implicit directory-walking resolution
 * to find puppeteer on its own — that implicit lookup is where this
 * broke the first time (Windows, PHP spawning a Node child process from
 * public/ as its working directory, global npm install not on that
 * resolution path). Requires `npm install puppeteer` run from the
 * Laravel project root (not `public/`, not a global -g install).
 *
 * The timeout comes from config('exports.pdf.timeout') rather than a
 * literal, so it can be tuned per environment without a code change. Its
 * purpose is to fail *usefully*: if Chrome/Puppeteer hangs on launch
 * (missing binary, stuck child process, antivirus interference — all real
 * things that happen during setup), Browsershot raises a catchable
 * ProcessTimedOutException instead of PHP's blunt execution-time
 * FatalError with no usable message. See that config file for why the
 * default is sized for a cold start, and for its relationship to the
 * queue worker's own timeout (GenerateTableExportPdfJob::$timeout).
 *
 * ->showBackground() is not optional here — Chrome's print/PDF engine
 * omits background colors and images by default (the same convention
 * browsers use for "print this page" to save ink), which would silently
 * drop the navy header, the striped rows, everything that makes this
 * report look like the approved design instead of plain black-on-white
 * text. Without this call the PDF would still "work", just not match.
 *
 * ->addChromiumArguments([...]) disables a handful of background
 * network/telemetry calls Chrome makes on startup by default even in
 * headless mode (Safe Browsing list updates, component/extension
 * update checks, sync, etc.), plus a few flags that speed up a cold
 * start and matter more once this runs on a real Linux server than on
 * a Windows dev machine (disable-gpu skips graphics-driver init headless
 * doesn't need; disable-dev-shm-usage avoids Chrome writing shared
 * memory to a tmpfs that's tiny by default on many Docker/Linux hosts,
 * a very common source of random crashes in containerized deployments;
 * disable-software-rasterizer skips a GPU fallback path headless will
 * never use). None of these change what gets rendered — same DOM, same
 * layout, same PDF — only how fast and how reliably Chrome gets there.
 * Combined with the template now being fully self-contained (no Google
 * Fonts network call either, see table-pdf.blade.php), this removes
 * every network-dependent variable from render time — what's left is
 * just Chrome's own startup + layout, which is fast and consistent.
 */
final class SpatiePdfExporter implements PdfExporterInterface
{
    public function toBytes(string $html, string $paperSize = 'a4'): string
    {
        return Pdf::html($html)
            ->format($paperSize)
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $browsershot
                    ->setNodeModulePath(base_path('node_modules'))
                    ->timeout((int) config('exports.pdf.timeout'))
                    ->showBackground()
                    ->addChromiumArguments([
                        'disable-extensions',
                        'disable-background-networking',
                        'disable-default-apps',
                        'disable-sync',
                        'disable-translate',
                        'metrics-recording-only',
                        'mute-audio',
                        'no-first-run',
                        'safebrowsing-disable-auto-update',
                        'disable-gpu',
                        'disable-dev-shm-usage',
                        'disable-software-rasterizer',
                    ]);
            })
            ->generatePdfContent();
    }
}
