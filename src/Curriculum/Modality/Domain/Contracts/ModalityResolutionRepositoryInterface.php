<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Contracts;

use Src\Curriculum\Modality\Application\DTOs\ModalityResolutionDTO;
use Src\Curriculum\Modality\Domain\Entities\ModalityResolution;
use Src\Shared\Document\Contracts\StoredDocument;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface ModalityResolutionRepositoryInterface
{
    /**
     * Inserts a new resolution and its attached document as one
     * transactional unit — the only point where the document is ever moved
     * to permanent storage. Always persisted, even if the eligibility gate
     * later rejects the assignment itself (a filed resolution is never
     * discarded, same "never deleted" criterion RC-02 already applies to
     * contradiction resolution).
     */
    public function create(int $courseId, int $modalityId, ModalityResolutionDTO $dto): ModalityResolution;

    /**
     * Every resolution on file for this course+modality pair — more than
     * one may legitimately coexist, so this is never narrowed to "the"
     * resolution.
     *
     * @return array<int, ModalityResolution>
     */
    public function allFor(int $courseId, int $modalityId): array;

    /**
     * The document attached to a specific resolution row, if any — every
     * resolution requires one at creation time, so a null result only ever
     * means the id itself doesn't exist.
     */
    public function findDocument(int $modalityResolutionId): ?StoredDocument;
}
