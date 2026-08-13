<?php

declare(strict_types=1);

namespace Src\Shared\Document\Contracts;

/**
 * Shared, entity-agnostic capability — like PdfExporterInterface /
 * ExcelExporterInterface — rather than a per-context port: attaching a file
 * to some polymorphic owner needs no domain knowledge of what that owner is.
 * RC-02 (Equivalency) is the first caller; RC-03 (ModalityResolution) is
 * expected to reuse this same port once it wires up its own document
 * attachment.
 */
interface DocumentRepositoryInterface
{
    /**
     * @param  class-string  $documentableType  the owning Eloquent model's class, e.g. App\Models\Equivalency::class
     */
    public function attach(AttachableDocument $document, string $documentableType, int $documentableId): void;
}
