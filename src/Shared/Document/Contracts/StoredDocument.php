<?php

declare(strict_types=1);

namespace Src\Shared\Document\Contracts;

/**
 * Read-side counterpart of AttachableDocument: the minimal data needed to
 * stream an already-persisted file back to the user (disk + path resolve
 * it, originalName/mimeType shape the download response), without exposing
 * the underlying Document Eloquent model past this port.
 */
final readonly class StoredDocument
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $originalName,
        public string $mimeType,
    ) {}
}
