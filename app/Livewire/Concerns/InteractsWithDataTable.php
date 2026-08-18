<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Which mode a listing should use — the decision, and why it is not "server
 * everywhere".
 *
 * Client mode is not a shortcut, it is a trade: pay one large payload up front,
 * and every sort, filter and page after that costs nothing because it never
 * leaves the browser. Below roughly 200 rows that trade is clearly worth it,
 * and moving those listings to server mode would make them measurably WORSE —
 * each sort would become a round trip where today it is free.
 *
 * Past ~200 rows the trade inverts: the payload starts to dominate the module
 * open, which is the interaction feature 002-perceived-performance prioritises.
 * Around 200 rows a table of this width serialises to roughly 40 KB, comparable
 * to the whole CSS bundle; the round trip that replaces it stays well inside the
 * 300 ms in-module budget.
 *
 * So the rule is a threshold, not a preference:
 *
 *   server  Courses (~800), Equivalencies (~500), Modality assignments (~800)
 *   client  Study plans (~10), Modalities (~10), Roles (~10), Permissions (~50)
 *
 * Before switching a listing to client mode, check its size at the target
 * volume against structural budget S-04; tests/Feature/Performance/QueryBudgetTest
 * enforces this and will fail if a large catalog is moved back.
 */
trait InteractsWithDataTable
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    public int $perPage = 10;

    public int $page = 1;

    public string $sortKey = '';

    public string $sortDir = 'asc';

    /**
     * Which side resolves search, sort and pagination. Components using this
     * trait declare $tableMode themselves, so the value is always set; the
     * accessor exists so the checks below read the same way everywhere.
     */
    public function tableMode(): string
    {
        return $this->tableMode;
    }

    public function isServerMode(): bool
    {
        return $this->tableMode() === 'server';
    }

    public function isClientMode(): bool
    {
        return ! $this->isServerMode();
    }

    /**
     * Server mode only — client mode resets its own page inside Alpine
     * and never touches this property over the wire.
     */
    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function updatingPerPage(): void
    {
        $this->page = 1;
    }

    /**
     * Server mode only. In client mode, sorting is handled by Alpine's
     * `sort()` method in resources/js/data-table.js and this method is
     * simply never wired up in the Blade view.
     */
    public function sort(string $key): void
    {
        $this->sortDir = $this->sortKey === $key && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortKey = $key;
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function refreshTable(array $rows): void
    {
        if ($this->isClientMode()) {
            $this->dispatch('data-table-refresh', rows: $rows);
        }
    }
}
