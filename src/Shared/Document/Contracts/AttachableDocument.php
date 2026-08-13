<?php

declare(strict_types=1);

namespace Src\Shared\Document\Contracts;

/**
 * Plain data shape for a file to attach to some polymorphic owner via
 * DocumentRepositoryInterface — primitives only, never a Livewire
 * TemporaryUploadedFile. Shared, not owned by any bounded context, so both
 * RC-02 (Equivalency) and a future RC-03 (ModalityResolution) depend on
 * this without depending on each other.
 *
 * `temporaryPath` deliberately still points at Livewire's own temporary
 * upload storage, not a permanent one: the file is only actually moved to
 * `disk`/`directory` inside DocumentRepositoryInterface::attach(), called
 * from inside the owning aggregate's own persistence transaction. This is
 * what lets a caller (e.g. RegisterEquivalencyUseCase) run its cycle/
 * contradiction checks first and never write a permanent file when either
 * one rejects the save — an abandoned temporary upload is simply cleaned up
 * by Livewire's own temporary-file garbage collection, never a hand-rolled
 * compensating delete.
 */
final readonly class AttachableDocument
{
    public function __construct(
        public string $originalName,
        public string $temporaryPath,
        public string $disk,
        public string $directory,
        public string $mimeType,
        public int $sizeBytes,
        public string $hashSha256,
        public ?int $uploaderId,
    ) {}
}
