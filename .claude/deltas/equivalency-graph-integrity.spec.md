# Equivalency Graph Integrity — Spec

## Objective

An equivalency between a course on an old study plan and a course on a new one is registered only when backed by an attached resolution document, in a stated direction (old plan counts toward new, new plan counts back toward old, or both), and only when doing so keeps two guarantees intact: the full set of registered equivalencies never contains a directed cycle of any length, and no two resolutions are ever left silently disagreeing about the same course pair and direction — a disagreement is caught and a human must explicitly say which one prevails, with the losing one marked Superseded rather than deleted or overwritten. This is the highest-weighted, most heavily scrutinized requirement in the project: partial cycle detection (short chains only) or partial contradiction handling caps grading at "Regular"; missing either entirely is "Insufficient."

## Scope

**In scope:**
- Registering an equivalency: source course, target course, direction, resolution number, and a mandatory attached resolution document.
- Cycle detection across the full graph of currently-active equivalencies, of any chain length, run before a new equivalency is allowed to save, returning the exact conflicting chain when one would be closed.
- Contradiction detection: rejecting a second equivalency for a course pair/direction that already has an active one, and the human-resolution flow that designates a winner and marks the loser Superseded.
- Authorization: one policy gating who may register equivalencies and who may resolve a contradiction, following the same permission-based pattern used elsewhere in this project.

**Out of scope / deferred:**
- Accreditation of test students once an equivalency is valid — a separate slice that consumes an active equivalency; this slice only guarantees the equivalency itself is valid, it does not touch student records.
- The modality catalog and its own resolution/document pattern — a separate slice; it happens to share the same "resolution number plus attached document" shape but has no other dependency on this one.
- The document upload mechanism itself (storage, size limits, virus scanning) — treated as an existing, given dependency this slice attaches to, not something this slice designs.
- Distinguishing "a genuinely conflicting resolution" from "the same fact resubmitted twice" as different outcomes — the current data model cannot represent an equivalency's "outcome" independently of the course pair/direction it names, so both cases are treated identically (see Risks). Revisiting this requires a data model change, which is explicitly deferred until a real case forces it.

## Technical context

**Already built (persistence layer):** the equivalency record itself, including a direction value (old-plan-counts-toward-new / new-plan-counts-toward-old / both), a status distinguishing active from superseded, and a self-reference letting a superseded record point at whichever record prevailed over it. The resolution number is a plain attribute of the equivalency record itself — there is no separate, dedicated resolution entity — and the actual resolution document is attached through this project's general-purpose polymorphic document-attachment mechanism (also used elsewhere for other kinds of attachments), not a bespoke one for equivalencies.

**Database-level backstops already in place, in addition to whatever this slice's domain layer adds:**
- A generated, indexed key derived from the course pair and direction — but only populated for records currently marked active — carries a uniqueness constraint. This is what physically prevents two active equivalencies from ever coexisting for the same course pair and direction; a superseded record's key is cleared so it no longer participates in that uniqueness check. The domain layer must produce its own specific, human-readable rejection *before* this constraint would fire, since the constraint's own failure is a generic, unfriendly database error.
- A source course can never equal a target course on the same record.
- The same resolution number may legitimately be reused across more than one course pair (one resolution document can approve several equivalencies at once) but never twice for the identical pair.

**Not yet built:** any domain, application, or presentation code for this slice — no cycle-detection service, no contradiction-resolution flow, no UI. This slice follows the same hexagonal shape as the study-plan-repository slice: a pure-PHP domain layer with no framework imports, an application layer of single-purpose use cases, an Eloquent-backed adapter translating persistence rows to domain entities, and a presentation layer (component, policy, routes) that only calls into the application layer.

**Corrected against this project's own architecture doc:** the doc previously described a dedicated "Resolution" entity shared between this slice and the modality-catalog slice, and left the relationship between `direction` and cycle-detection edge orientation as an open, unresolved design question. Both are now corrected — see below for the resolved edge-orientation rule, and note there is no dedicated resolution entity: the resolution number lives directly on the equivalency record, backed by the shared document-attachment mechanism for the file itself.

## Implementation

1. Domain layer: an `Equivalency` entity (course pair, direction, resolution number, status, superseding reference) with domain exceptions for its own rule violations (self-referencing pair, missing document at the boundary). A repository contract exposing the currently-active edge set for graph traversal and the operations needed to persist a new equivalency or transition an existing one to superseded.
2. Two separate pure-PHP domain services, not entity methods: a cycle-detection service (DFS-based, tracking the recursion stack, operating on a graph of edges — not hardcoded for small cases) and a contradiction-detection service (checks whether an active equivalency already exists for the candidate's course pair and direction). Both take the graph/candidate as plain input and return a result value, with no database access of their own.
3. A small, separately-tested pure function mapping `direction` to graph edge orientation: the edge always follows the accreditation-flow meaning of `direction` — the direction in which passing one course flows to accredit the other — not a fixed reading of which course is stored as source and which as target. The "new plan counts back toward old" case produces a reversed edge relative to the raw stored course pair; the bidirectional case produces both directions as edges. Only currently-active equivalencies contribute edges — a superseded one is dead and must not be able to cause a phantom cycle rejection.
4. Application layer: a "register equivalency" use case that, in order, validates a document is attached, runs cycle detection (reject with the full chain if it would close one), runs contradiction detection (reject and surface both conflicting records if one already exists for the pair/direction), and only then persists — wrapped in a single transaction together with the document attachment, so a mid-flow rejection never leaves an orphaned uploaded file or a half-written record. A separate "resolve contradiction" use case takes an explicit winner designation and transitions the loser to superseded (whichever of the two records that is — the newly submitted one, or the one that was already active), always persisting the loser rather than discarding it, and pointing its superseding reference at the winner.
5. Presentation layer: one component covering registration and, when a contradiction is detected, the resolution decision; an authorization policy gating both the registration and the resolution actions; routes.

## Config / secrets

Not applicable — no new environment variables or credentials introduced by this slice.

## Acceptance criteria

- [ ] Attempting to save an equivalency without an attached resolution document is blocked with the specific message: "You must attach the resolution that approves this equivalency."
- [ ] Saving with the direction "old plan counts toward new" persists and displays that exact direction.
- [ ] A new equivalency that would close a directed cycle with existing active equivalencies is rejected, and the rejection includes the exact conflicting chain in order, by human-readable course identifier, not an internal ID. This is verified for chains longer than three courses, not only short ones.
- [ ] A second submission for a course pair/direction that already has an active equivalency is blocked, both conflicting resolutions are shown, and the save proceeds only once an explicit winner is designated.
- [ ] After a contradiction is resolved, the losing record — whichever one it is, the new submission or the previously-active one — is persisted with status Superseded and its superseding reference pointing at the winner; it is never deleted.
- [ ] A superseded equivalency does not block a later, unrelated equivalency from being registered through what would otherwise look like a cycle passing through it.
- [ ] Registering an equivalency and resolving a contradiction are both denied for a user lacking the corresponding permission, and allowed for one holding it.

## How to test

Domain-level, highest priority given this is the graded centerpiece: plain unit tests against the pure-PHP cycle-detection service and contradiction-detection service, with no database and no framework involved — construct graph fixtures directly, covering no cycle, a two-node cycle, a long chain (five or more courses) cycle, a cycle that only exists through a bidirectional edge's reverse direction, and a superseded edge that must not count toward a cycle. Unit test the direction-to-edge-orientation mapping on its own as well, since it is the resolved design decision most likely to silently regress.

Feature-level: exercise the registration and contradiction-resolution component through Livewire's component-testing API, authenticated as a user built with the project's existing permission-granting test helper, covering the missing-document rejection, the cycle rejection (asserting the returned chain), and the full contradiction flow (submit a conflicting equivalency, assert it is blocked, resolve it, assert the loser is superseded).

## Risks / edge cases

- **The edge-orientation-follows-direction rule is easy to get backwards**, specifically for the "new plan counts back toward old" case, where the graph edge must point opposite to how the course pair is stored. A quick read of the stored columns alone looks like it should always point one way; it does not. Call this out explicitly wherever the cycle-detection service is implemented, not only here.
- **Contradiction detection, as currently scoped, cannot distinguish a genuinely conflicting resolution from the same fact resubmitted twice.** The data model has no way to represent an equivalency's "outcome" independently of the course pair and direction it names, so both cases hit the same blocked-pending-human-decision path. Changing this requires a data model change and is explicitly out of scope until a real case forces it.
- **Cycle detection must run against the full, current graph on every check, not a cached one** — a stale in-memory graph computed once and reused risks missing a cycle formed by a concurrent registration. The edge set backing the check must be loaded fresh each time.
- **A resolution document upload succeeding while the subsequent cycle or contradiction check then fails must not leave an orphaned file on disk** — the transactional guarantee has to cover the storage side effect, not only the database rows.
- **The database's own uniqueness backstop on active course-pair/direction combinations is a silent, generic-error fallback, not a substitute for the domain-level contradiction flow.** If the domain layer's own check has any gap, a user sees a raw database error instead of the specified contradiction-resolution experience — the domain layer must be the path that actually runs in normal operation.
