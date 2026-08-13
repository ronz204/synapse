<?php

declare(strict_types=1);

namespace Src\Shared\Document\Infrastructure;

use App\Models\Document as DocumentModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Shared\Document\Contracts\AttachableDocument;
use Src\Shared\Document\Contracts\DocumentRepositoryInterface;
use Src\Shared\Document\Contracts\StoredDocument;

final class EloquentDocumentRepository implements DocumentRepositoryInterface
{
    /**
     * Fixed type: this adapter is only ever handed a resolution document
     * today (RC-02's equivalencies, and RC-03's modality resolutions once
     * wired up) — a selector belongs on the caller's side once a second
     * distinct document type genuinely needs one, not speculatively here.
     */
    private const DOCUMENT_TYPE = 'Resolución';

    /**
     * Moves the file from Livewire's temporary upload storage to its
     * permanent disk/directory only here — the one point every caller
     * reaches only after its own domain checks have already passed (see
     * AttachableDocument's docblock for why that ordering matters).
     */
    public function attach(AttachableDocument $document, string $documentableType, int $documentableId): void
    {
        $extension = pathinfo($document->originalName, PATHINFO_EXTENSION) ?: 'bin';
        $storedPath = trim($document->directory, '/').'/'.Str::uuid().'.'.$extension;

        Storage::disk($document->disk)->put($storedPath, file_get_contents($document->temporaryPath) ?: '');

        DocumentModel::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $document->uploaderId,
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'document_type' => self::DOCUMENT_TYPE,
            'original_name' => $document->originalName,
            'disk' => $document->disk,
            'path' => $storedPath,
            'mime_type' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'hash_sha256' => $document->hashSha256,
        ]);
    }

    public function findFor(string $documentableType, int $documentableId): ?StoredDocument
    {
        $document = DocumentModel::query()
            ->where('documentable_type', $documentableType)
            ->where('documentable_id', $documentableId)
            ->latest()
            ->first();

        return $document ? new StoredDocument($document->disk, $document->path, $document->original_name, $document->mime_type) : null;
    }
}
