<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes queued-PDF-export files (Src\Shared\Export\Infrastructure\Jobs\GenerateTableExportPdfJob)
 * older than an hour — catches exports a user never came back to
 * download, since InteractsWithExports::downloadQueuedPdf() only cleans
 * up the file it actually streams. The Cache-backed status entry
 * expires on its own 30-minute TTL either way; this only prevents the
 * underlying files from accumulating on disk forever. Scheduled hourly
 * in bootstrap/app.php.
 */
#[Signature('app:prune-pdf-exports')]
#[Description('Delete queued PDF exports older than an hour that were never downloaded')]
class PrunePdfExports extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHour()->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('exports') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale PDF export(s).");

        return self::SUCCESS;
    }
}
