<?php

declare(strict_types=1);

namespace Src\Curriculum\Equivalency\Domain\Events;

/**
 * Raised after an equivalency is durably persisted as Active — either a
 * brand-new registration or a contradiction resolved in the candidate's
 * favor. Plain PHP on purpose (no Illuminate\Contracts\Events import): the
 * Infrastructure adapter that dispatches this only does so once its
 * DB::transaction() has already returned, so a rollback never fires it —
 * the same guarantee ShouldDispatchAfterCommit would give, without the
 * domain-events layer importing Laravel.
 */
final readonly class EquivalencyBecameActive
{
    public function __construct(public int $equivalencyId) {}
}
