<!--
Sync Impact Report
Version change: [TEMPLATE] → 1.0.0 (initial ratification — first time this file is filled in)
Modified principles: n/a (template placeholders replaced, no prior ratified content existed)
Added sections:
  - Core Principles I–VI (Domain-Framework Isolation, Equivalency Graph Integrity, Acceptance
    Criteria as Tests, Non-Destructive History, Contract-First Boundary, Whole-System Understanding)
  - Roles & Responsibilities (Ian's scope + Roney's scope + shared checklist items)
  - Development Workflow & Quality Gates
  - Governance
Removed sections: none
Templates checked for alignment:
  - .specify/templates/plan-template.md — generic, no constitution-specific references to update
  - .specify/templates/spec-template.md — generic, no constitution-specific references to update
  - .specify/templates/tasks-template.md — generic, no constitution-specific references to update
  - .specify/templates/checklist-template.md — generic, no constitution-specific references to update
Follow-up TODOs: none — all placeholders resolved from support/Responsabilities.md,
  .claude/docs/{overview,approach,modules}.md, and .claude/rules/coding.md.
-->

# Curricular Repository System Constitution

## Core Principles

### I. Domain-Framework Isolation (NON-NEGOTIABLE)

Domain logic — study plan structure rules, the equivalency graph, cycle detection, contradiction
detection, direction/accreditation matching, and modality-resolution rules — MUST live in code
that does not import Laravel, Livewire, or Alpine.js classes. Laravel, Livewire, and Eloquent MUST
only appear as adapters implementing domain-defined ports (repositories, gateways); the dependency
direction never reverses. Self-test: deleting the framework must not break the domain package's
compilation. Any change that introduces a `use Illuminate\...` or `use Livewire\...` statement
inside the domain namespace is rejected outright — there is no "just this once."

Rationale: this boundary is graded explicitly and is the architectural backbone the four functional
requirements (RC-01–RC-03) are built on; a single violation undermines the reason the hexagonal
design exists at all.

### II. Equivalency Graph Integrity Is Sacred

Two invariants override convenience, deadline pressure, or "it mostly works":

1. No directed cycle may ever be persisted in the equivalency graph, regardless of chain length.
   Detection MUST be a real DFS/recursion-stack algorithm — never a hardcoded 2–3 node check.
2. No two `Active` equivalencies may silently contradict for the same
   `(source_course, target_course, direction)` triple. A conflict MUST block the save and force an
   explicit human decision; the losing resolution is marked `Superseded`, never deleted or
   overwritten.

Rejections MUST surface the exact failing chain (e.g. `A → B → C → A`) or both conflicting
resolutions — a generic "error" message does not satisfy this principle.

Rationale: getting this wrong costs a real student a semester, and graph integrity is its own
dedicated rubric line item. No other principle in this document may weaken it.

### III. Acceptance Criteria Are the Tests, Not a Description

Every functional requirement (RC-01, RC-02, RC-02b, RC-03) ships with acceptance criteria recorded
in `.claude/docs/approach.md`. A requirement whose acceptance criteria are not encoded as passing
Pest tests counts as **not implemented**, regardless of whether the code "kind of works." Domain
services — cycle detection, contradiction detection, direction matching — MUST have unit tests
that run without booting Laravel, written before the Livewire/Eloquent adapters around them, per
the domain-first build order.

### IV. Non-Destructive History

Superseded equivalencies, historical accreditation records, and expired modality resolutions MUST
remain queryable forever. Hard deletes are prohibited for all three record types. A status
transition (`Active` → `Superseded`, an expired validity window) is the only allowed way to retire
a record — never row removal.

### V. Contract-First Across the Domain/Adapter Boundary

Wherever a Livewire component meets a domain use case — the equivalency form, the modality
assignment screen, the academic-history view — the DTO/data contract between them MUST be agreed
by both people building each side *before* either starts implementing. This mirrors the explicit
`[Ambos]` checklist items in `support/Responsabilities.md` for every requirement with a UI-facing
surface (RC-02, RC-02b, RC-03), and exists to prevent integration rework discovered late.

### VI. Whole-System Understanding, Not Silos

Every team member must be able to explain any part of the system, not only the checklist items
assigned to them — the oral defense can point at any function and question any decision, and
grades distributed understanding explicitly. `.claude/docs/` MUST stay current enough that reading
it substitutes for tribal knowledge of the other person's half of the codebase.

## Roles & Responsibilities

Scope is drawn from `support/Responsabilities.md`. This project is built by two people; **Ian's
responsibilities in this repository are the ones below**, and this constitution's UI/adapter-facing
obligations (Principle V) bind Ian's work directly:

- **RC-01 (Study Plan Repository):** Livewire view for the full plan structure (levels → courses →
  prerequisites); creation form (program + Vigente/Terminal classification); Alpine.js component to
  add/remove courses and prerequisites dynamically; indicator of active test students per plan/level.
- **RC-02 (Equivalencies):** Livewire form for source/target course, direction, resolution number,
  and PDF upload; PDF upload/preview with validation and UX; UI error messages that render the
  detected cycle chain and the conflicting-resolutions pair verbatim from the domain layer; history
  view of equivalencies (direction, resolution, Active/Superseded status).
- **RC-02b (Accreditation):** Internal academic history view per student (accredited course +
  resolution number); explicit rendering of accreditation direction (origin → destination) and its
  source equivalency; empty/error states (no history, no accreditations); demo seeder/loader of test
  students with passed courses.
- **RC-03 (Modality Catalog):** Admin screen for the modality catalog (create/edit the
  "requires resolution" flag); modality-assignment form for a course, including resolution upload
  and validity dates; visible rejection message when no valid modality resolution exists.
- **Shared with the domain owner (`[Ambos]` items):** agreeing each RC's DTO/data contract before
  either side starts coding (Principle V); defining demo/test data that reproduces cycles,
  contradictions, valid vs. superseded equivalencies, and the reverse-direction accreditation
  negative case.

The domain owner (Roney, per `support/Responsabilities.md`) is responsible for domain entities,
the equivalency graph, cycle/contradiction algorithms, use cases, migrations, and Eloquent-backed
repository adapters behind the ports the Livewire layer consumes. Ian's Livewire/Alpine.js code
depends on those ports and DTOs — it never re-implements graph logic client-side or bypasses the
use case layer to talk to Eloquent directly.

## Development Workflow & Quality Gates

- **Three mandatory progress checkpoints** are a hard gate: without all three approved, the team
  cannot present the final defense, regardless of how complete the code is. Build incrementally and
  verifiably against these checkpoints, not as a last-minute assembly job.
- **Domain-first build order:** model Plan, Level, Course, Prerequisite, Equivalency, Resolution,
  and Modality as pure domain objects with unit tests before wiring any Livewire component or
  Eloquent migration (see `.claude/docs/approach.md`, "Suggested build order").
- **AI Decision Diary** is updated as work happens, not reconstructed at the end. Each entry
  records what was asked, what was accepted/rejected and why, and includes at least one real,
  verifiable case where AI output was wrong and had to be corrected. Generic entries ("AI helped
  with the code") fail this requirement outright.
- **Before finalizing any PHP change:** run `vendor/bin/pint --dirty --format agent`, then
  `php artisan test --compact` filtered to the affected area, per the Laravel Boost test-enforcement
  rule already in effect for this codebase.
- **Anticipate blast radius before running a change.** This is graded directly in the oral defense
  and is a working habit expected throughout development, not a defense-day performance.

## Governance

This constitution supersedes ad hoc conventions. `.claude/rules/*.md` and `.claude/docs/*.md`
provide the operative detail beneath it (coding style, Eloquent conventions, Livewire component
shape, PHP language practices) and MUST NOT contradict it; if a conflict is found, this document
wins and the conflicting rule file is updated to match.

**Amendments:** proposed via `/speckit-constitution`. Because every principle here binds both
people's checklists (Principle V, Roles & Responsibilities), an amendment requires agreement from
both the domain owner and Ian before it is ratified. Version bumps follow semantic versioning:
MAJOR for incompatible governance/principle removals or redefinitions, MINOR for a new principle
or materially expanded guidance, PATCH for wording/clarification fixes. `Last Amended` is updated
to the date of the change; `Ratified` never changes after initial adoption.

**Compliance review:** before any RC (RC-01, RC-02, RC-02b, RC-03) is marked complete, verify it
against the acceptance criteria in `.claude/docs/approach.md` and the invariants in
`.claude/docs/modules.md` — a requirement failing its acceptance criteria is not implemented, per
Principle III, even if a checkpoint demo made it look functional.

**Version**: 1.0.0 | **Ratified**: 2026-08-02 | **Last Amended**: 2026-08-02
