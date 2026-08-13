<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Contracts;

use App\Enums\EquivalencyDirection;
use Src\Curriculum\Equivalency\Domain\Entities\Equivalency;
use Src\Curriculum\Equivalency\Domain\ValueObjects\CourseNode;
use Src\Curriculum\Equivalency\Domain\ValueObjects\EquivalencyEdge;
use Src\Shared\Document\Contracts\AttachableDocument;

/**
 * Port (in the Hexagonal sense) that Infrastructure adapters must
 * implement. The Domain and Application layers depend only on this
 * abstraction — never on Eloquent, the database, or any concrete driver.
 */
interface EquivalencyRepositoryInterface
{
    public function find(int $id): ?Equivalency;

    /**
     * @return array<int, Equivalency>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Equivalency>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function courseNode(int $courseId): CourseNode;

    /**
     * Full graph of currently-Active equivalencies, oriented per direction —
     * loaded fresh on every call, never cached, so a concurrent registration
     * is never missed by a stale in-memory graph.
     *
     * @return array<int, EquivalencyEdge>
     */
    public function activeGraph(): array;

    /**
     * The one Active equivalency, if any, already registered for this exact
     * (source, target, direction) triple — what the contradiction check is
     * keyed on.
     */
    public function findActiveForPair(int $sourceCourseId, int $targetCourseId, EquivalencyDirection $direction): ?Equivalency;

    /**
     * Inserts a brand-new Active equivalency and its resolution document as
     * one transactional unit — the only point where the document is ever
     * moved to permanent storage.
     */
    public function register(Equivalency $equivalency, AttachableDocument $document): Equivalency;

    /**
     * Persists the contradiction-resolution outcome as one transactional
     * unit: the candidate is inserted (Active if it prevails, Superseded
     * pointing at the existing record otherwise), and if the candidate
     * prevails, the existing record is transitioned to Superseded pointing
     * at the candidate. Neither record is ever discarded or deleted.
     */
    public function resolveContradiction(Equivalency $candidate, AttachableDocument $candidateDocument, int $existingEquivalencyId, bool $candidatePrevails): Equivalency;
}
