# Modality Catalog — Spec

## Objective

A catalog of teaching modalities, each flagged whether it requires a backing resolution to use, is maintained by an admin; a course can be assigned a modality only when either that modality doesn't require a resolution, or a currently-valid, document-backed resolution is on file for that specific course and modality; a course with no modality specified always defaults to the in-person modality.

## Scope

**In scope:**
- Full catalog CRUD: creating and editing a modality's name and its "requires a resolution" flag, beyond the five values the catalog ships with.
- Assigning a modality to a course, backed by a resolution (number, approving body, validity window, mandatory attached document) when the modality requires one.
- The write-time validation gate: rejecting an assignment to a resolution-requiring modality when no currently-valid resolution exists for that course/modality pair.
- Defaulting a course with no modality specified to the in-person modality, as an explicit application rule rather than an incidental default.
- Authorization: one policy gating catalog maintenance and one gating course-modality assignment, following this project's established permission-based pattern.

**Out of scope / deferred:**
- Any continuous or background re-validation of a course's already-assigned modality against currently-valid resolutions. The rule here is a write-time gate only — see Risks for why this boundary is deliberate, not an oversight.
- RC-01, RC-02, and RC-02b internals. This slice depends only on the course concept already defined elsewhere and references it by identifier; it has no dependency on the equivalency graph or on accreditation in either direction.

## Technical context

**Already built (persistence layer):** the modality catalog itself (name, unique; a boolean flag for whether it requires a resolution) and the course-modality-resolution record (course, modality, resolution number, approving body, a validity window with a start date and an optional end date), with a query scope already implementing the "currently valid" window check (start date has passed, and either there's no end date or it hasn't been reached yet) — this is a ready-made building block for the write-time gate, not something to reimplement. A seeder inserts the required catalog values, with the in-person modality (the only one that doesn't require a resolution) inserted first. A course's modality reference is nullable with no database-level default; the default-to-in-person behavior currently exists only as a convenience in test data setup, not as an enforced application rule — that has to change as part of this slice.

**A genuine implementation gap, not just a stale doc:** the course-modality-resolution record has no document-attachment relation wired up yet, unlike the equivalency slice, which already attaches to this project's shared, general-purpose polymorphic document mechanism. A resolution document is mandatory here too — wiring that attachment is part of this slice's own work, not something already in place to build on top of.

**An existing database-level backstop worth reusing, same pattern as this project's other slices:** a course's modality reference is a restrict-on-delete foreign key — deleting a modality still referenced by any course already fails at the database level. The domain layer should turn that into a specific, friendly rejection before it ever reaches that raw constraint, the same "domain layer fronts the database backstop" pattern already established elsewhere in this project.

**Corrected against this project's own architecture doc:** the doc previously assumed a resolution concept shared between the equivalency slice and this one. Neither actually has a dedicated, separate resolution entity — each stores its own resolution number as a plain attribute directly on the record it belongs to (this slice's record additionally carries an approving body and a validity window that the equivalency slice's does not), and each is separately backed by the same shared document-attachment mechanism for the file itself. The equivalency slice's use of that mechanism is already wired; this slice's is not yet.

**Not yet built:** any domain, application, or presentation code for this slice — no catalog CRUD, no assignment flow, no UI.

## Implementation

1. Wire the missing document-attachment relation on the course-modality-resolution record, mirroring how the equivalency slice already attaches to the same shared mechanism.
2. Domain layer: a `Modality` catalog-entry entity (name, requires-resolution flag) with its own repository contract and exceptions (in-use-cannot-delete), and a `ModalityResolution` entity (course, modality, resolution number, approving body, validity window) with its own repository contract, referencing `Course` only by identifier. A small pure function answering "is there a currently-valid resolution for this course/modality pair, given a target moment" — reusing the same validity-window logic already present in the persistence layer's query scope, expressed as a pure check rather than a database query at the point it's actually evaluated.
3. A small pure function for the default-to-in-person rule: given no modality specified, resolve and return the in-person modality by name, not by an assumed identifier.
4. Application layer: use cases for catalog maintenance (create/edit a modality, delete guarded by the in-use check) and for course-modality assignment (validate document attachment, validate the resolution-required gate, persist).
5. Presentation layer: one component for catalog maintenance, one for assigning a modality (with its resolution) to a course, an authorization policy for each, and routes.

## Config / secrets

Not applicable — no new environment variables or credentials introduced by this slice.

## Acceptance criteria

- [ ] Attempting to assign a course a modality flagged as requiring a resolution, with no currently-valid resolution on file for that course/modality pair, is rejected with the specific message: "No valid modality resolution exists for this course."
- [ ] A course with no modality specified is assigned the in-person modality by default.
- [ ] A modality resolution cannot be saved without an attached document.
- [ ] Once a currently-valid resolution exists for a course/modality pair, assigning that modality to that course succeeds and is visibly reflected on the course.
- [ ] An expired resolution does not count as currently valid for a new assignment attempt.
- [ ] An admin can create and edit a modality catalog entry; attempting to delete one still referenced by a course is rejected with a specific message, not a raw database error.
- [ ] Catalog maintenance and course-modality assignment are both denied for a user lacking the corresponding permission, and allowed for one holding it.

## How to test

Domain-level: unit test the "can this modality be assigned to this course right now" rule directly against plain input — a modality's requires-resolution flag, a small set of resolution records with their validity windows, and a target moment — with no database and no framework involved. Cover: the modality doesn't require a resolution (always allowed), it requires one and a valid one exists, it requires one and only an expired one exists, it requires one and none exists at all. Unit test the default-to-in-person rule on its own as well.

Feature-level: exercise catalog CRUD and the course-modality-assignment flow through Livewire's component-testing API, authenticated as a user built with the project's existing permission-granting test helper — covering the missing-resolution rejection, the missing-document rejection, a successful assignment backed by a valid resolution, the default-on-no-modality-specified behavior, and the delete-blocked-while-in-use rejection.

## Risks / edge cases

- **The validity check is a write-time gate only, deliberately not re-evaluated later.** A course's already-assigned modality is not automatically revisited when its backing resolution's validity window later lapses, or when the catalog's requires-resolution flag changes afterward — reverting or flagging a course's already-applied state as a side effect of an unrelated later change is a materially bigger behavior than what's asked for, and is explicitly out of scope. A course can end up displaying a modality whose backing resolution has since expired; that's the deliberate boundary, not a bug, but the interface should make this legible (e.g., surfacing the resolution's validity window alongside the assignment) rather than presenting it as unconditionally current.
- **More than one resolution can legitimately exist for the same course/modality pair** (e.g., a renewal filed before the previous one expires) — the write-time check only needs at least one currently-valid one to exist, not exactly one; this is normal, not an error condition.
- **The document-attachment relation doesn't exist yet and has to be added as part of this slice** — don't assume it can simply be reused the way the equivalency slice's already-wired one can; verify it actually works before relying on the mandatory-document acceptance criterion in tests.
- **Deleting an in-use modality is already blocked at the database level regardless of what the domain layer does** — the domain-level check exists to produce a better rejection message, not because it's the only thing preventing the deletion; skipping it doesn't create a data-integrity risk, but does leave a raw database error as the user-facing result, which fails this slice's own acceptance criteria.
