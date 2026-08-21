<?php

declare(strict_types=1);

namespace Src\Shared\Export\Contracts;

/**
 * Port for rendering HTML to PDF bytes. Takes a raw HTML string, not a
 * Blade view name + data — that keeps this contract from assuming Blade
 * even exists. Rendering the view to a string is the Presentation
 * layer's job (RoleComponent/PermissionComponent, when we wire this in);
 * this port's only concern is "HTML in, PDF bytes out".
 *
 * Returns raw bytes rather than an HTTP response on purpose: the only
 * caller is a queued job (Src\Shared\Export\Infrastructure\Jobs\GenerateTableExportPdfJob —
 * see performance.md P1, a synchronous Browsershot render was blocking
 * an HTTP worker for 7-20s per export), and a queue worker has no
 * request/response cycle to hang a StreamedResponse off. The job itself
 * writes the bytes to storage; InteractsWithExports::downloadQueuedPdf()
 * is what turns them into an HTTP download once the export is ready.
 *
 * Same reasoning as ExcelExporterInterface: one shared port, not one
 * per entity — turning a rendered page into a PDF needs no domain
 * knowledge. Only Infrastructure/SpatiePdfExporter.php is allowed to
 * import Spatie/Browsershot types.
 */
interface PdfExporterInterface
{
    /**
     * @param  string  $paperSize  Any size the underlying renderer accepts
     *                             ('a4', 'letter', 'legal', ...). Defaults to 'a4' since
     *                             that's the more common case; a specific report's own
     *                             design dictates otherwise (e.g. this app's report template
     *                             is built for US Letter to match its @page CSS rule) and
     *                             passes it explicitly — the port stays generic, only the
     *                             call site knows which size its own HTML was designed for.
     */
    public function toBytes(string $html, string $paperSize = 'a4'): string;
}
