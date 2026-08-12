# Study Plan Repository — Spec

## Objective

Given a program, a study plan's structure (levels, the courses within each level, intra-plan prerequisites between those courses) is registered, validated, and queryable: a plan is classified Active or Terminal (Terminal requires an enrollment closing date), a prerequisite is rejected at save time unless both its courses are actually linked to that same plan, and the count of active test students per plan/level can be queried. This is the structural foundation the equivalency graph (cross-plan equivalencies) and the modality catalog (course modality assignment) build on — both reference the same `Course` concept this slice owns.

## Scope

**In scope:**
- CRUD for `Program`, `StudyPlan`, `Level`, `Course`, and `Prerequisite`, including the extra `Course` attributes already present in the schema: `is_service`, `is_bottleneck`, `requires_laboratory`, `laboratory_type`, `modality_id` (default resolved by name to "Presencial"), `active` — these are treated as in-scope fields for the CRUD, not placeholders.
- Conditional-required validation: `enrollment_closing_date` is mandatory when a plan's classification is Terminal, forbidden/irrelevant otherwise.
- Prerequisite validation: a prerequisite can only be saved when both its required and dependent courses are linked (via the course-level relationship) to the specific plan the prerequisite is being registered against.
- A query returning the count of active test students per plan/level, backed by the already-existing student/enrollment data.
- Authorization: one policy per aggregate (mirroring the existing Role/Permission policy pattern), gated by the project's existing role/permission system — not left open to any authenticated user.

**Out of scope / deferred:**
- Cross-plan equivalency registration and its cycle/contradiction validation — a separate slice that *consumes* `Course` by ID, described elsewhere.
- Accreditation of test students via equivalency — depends on the equivalency slice above, not this one.
- Modality catalog management and modality-resolution validation — a separate slice that also *consumes* `Course` by ID; this slice only owns the `modality_id` pointer as a plain attribute of Course, not the rules around when a modality assignment requires a resolution.
- Whether `Program` needs a dedicated CRUD UI of its own, as opposed to being seeded/administered another way — not established by the current requirements; left open until a concrete need forces the decision.

## Technical context

**Already built (persistence layer):** the database schema and Eloquent models for `Program`, `StudyPlan`, `Level`, `Course`, the course-to-level link, `Prerequisite`, and the test-student/enrollment tables the active-count query reads from, plus a backed enum for plan classification and one for laboratory type, and model factories for all of the above (including a "Terminal plan" factory state).

**Schema facts that matter for how this slice is modeled:**
- A course belongs to a program, not to a single plan or level — the same course row is intentionally reusable across multiple plans/levels. The number of credits a course is worth is **not** a property of the course itself; it's a property of its link to a specific level (the same course can carry different credits in different levels/plans).
- A course flagged as a shared "service" course has no program; a non-service course must have one — this is enforced as a database check constraint today and should be mirrored as an explicit, named domain rule rather than left to surface as a raw constraint violation.
- A prerequisite is stored against a specific plan directly (its own plan reference, not derived transitively through course → level → plan), plus references to its required and dependent courses. Given courses aren't level/plan-exclusive, "the prerequisite's courses exist within the plan" means both courses are linked to *some* level of that specific plan — not that the courses themselves belong to the plan.
- A plan's Terminal-requires-closing-date rule is already enforced at the database level via a check constraint, in addition to whatever validation the domain/form layer adds — both layers matter; the database constraint is a backstop, not a substitute for the domain-level rejection with a clear message.
- Levels are ordered/numbered per plan (unique per plan) with no separate name field.
- Course codes are unique across the whole system, not scoped to a program or plan — this identifier is the join point the equivalency slice and the modality slice both key off of, so its uniqueness is a cross-slice invariant, not just a concern local to this one.

**Domain modeling decisions:**
- `Course` is modeled as its own aggregate, independent of any specific plan — because it's reused across plans and is also the concept the equivalency slice and the modality slice both reference. `StudyPlan`, `Level`, and `Prerequisite` reference a course only by its identifier; they never reach into a course's internals, and a course aggregate never reaches into a plan's.
- `StudyPlan` is modeled as a single aggregate that owns `Level` and `Prerequisite` as child entities within its own boundary — both are genuinely exclusive to one plan, unlike `Course`.
- This slice follows the same hexagonal shape as the one existing bounded-context reference implementation in this codebase: a pure-PHP domain layer (entities constructed via named factory methods, one repository-contract interface per aggregate, domain-specific exceptions for rule violations), an application layer of single-purpose use cases (one per write/read operation) operating on the contract, an Eloquent-backed adapter implementing that contract and translating between the persistence model and the domain entity, and a presentation layer (one Livewire component per aggregate's screen, an authorization policy, routes) that only calls into the application layer. Domain code must not import Eloquent or Livewire classes, per this project's mandatory Hexagonal/DDD constraint.
- A dedicated bounded-context grouping for this slice's aggregates (separate from the existing identity/access one) is expected, with `Course` and `StudyPlan` as sibling aggregates inside it, matching how the existing identity/access context hosts more than one sibling aggregate. The exact context name is not yet finalized — treat it as a naming detail to confirm at implementation time, not a blocker to this spec.

## Implementation

1. Domain layer: `Course` aggregate (entity + repository contract + exceptions for code-uniqueness and service/program-consistency violations) and `StudyPlan` aggregate (entity owning `Level` and `Prerequisite` as child entities + repository contract + exceptions for the Terminal/closing-date rule and the prerequisite-scoping rule). Both pure PHP, unit-testable without Laravel booted.
2. Application layer: one use case per write/read operation for each aggregate (create/update/list/find, plus delete where meaningful — deletion here means deactivation via the `active` flag or a plan's classification transition, never a row delete, since the equivalency and modality slices may still reference historical courses/plans).
3. Infrastructure layer: an Eloquent-backed repository adapter per aggregate, translating between the persistence models and the domain entities.
4. Presentation layer: one Livewire component per aggregate (plan structure view/editor covering levels, courses-per-level, and prerequisites; course catalog view/editor), an authorization policy per aggregate wired the same way the existing reference policy is, and routes.
5. The active-student-count query is exposed alongside the plan structure view, reading directly from the existing student/enrollment data grouped by plan and level.

## Config / secrets

Not applicable — no new environment variables or credentials introduced by this slice.

## Acceptance criteria

- [ ] Selecting a plan displays its full structure (levels → courses → prerequisites) together with its classification.
- [ ] A Terminal plan additionally displays its enrollment closing date next to the classification.
- [ ] Attempting to save a plan classified Terminal without an enrollment closing date is blocked, not defaulted or silently allowed.
- [ ] Attempting to save a prerequisite whose required or dependent course is not linked to the plan being edited is blocked with a specific rejection, not a silent drop or a warning that still allows the save.
- [ ] Attempting to save a prerequisite where the required and dependent course are the same course is blocked.
- [ ] Attempting to save a course with a code that already exists elsewhere in the system is blocked.
- [ ] Attempting to save a non-service course without a program is blocked; a service course is allowed without one.
- [ ] A course with no modality specified defaults to "Presencial".
- [ ] Querying a given plan and level returns the correct count of currently active test students enrolled at that plan/level.
- [ ] Every write path above is denied for a user lacking the corresponding permission, and allowed for one holding it.

## How to test

Feature-level: exercise each Livewire component through Livewire's component-testing API, authenticated as a user built with the project's existing permission-granting test helper — call the create/update actions with both valid and invariant-violating input and assert the resulting success/forbidden/validation-error response, following the same shape as the existing feature tests for the reference bounded context (`Livewire::actingAs($user)->test(Component::class)->call(...)->assertOk()` / `->assertForbidden()`).

Domain-level: unit test each domain rule (Terminal-requires-date, prerequisite-scoping, course code uniqueness, service/program consistency) directly against the pure-PHP entities, with no database and no Laravel framework booted — these must be expressible as plain input-in/result-out tests, per this project's rule that domain logic stays pure and testable in isolation.

Run via `php artisan test --compact --filter=<relevant filter>` once the tests exist.

## Risks / edge cases

- **Prerequisite scoping is relative to a plan, not to the courses themselves.** Because a course can be linked to several plans, a prerequisite pair that's valid in one plan says nothing about whether it's valid in another — the scoping check must always be evaluated against the specific plan the prerequisite is being saved into, never cached or assumed from a prior save.
- **Credits belong to the course-level link, not the course.** Any read path that reports "this course is worth N credits" without qualifying which level/plan it's being viewed through is answering an ambiguous question — there is no single credit value for a course in isolation.
- **Course reuse means a course's lifecycle outlives any one plan.** Deactivating or editing a course used by multiple plans affects all of them at once; there is no per-plan override of a course's own attributes (name, labs required, etc.) — only credits vary per link.
- **The database check constraints are a backstop, not the primary validation surface.** If the domain/application layer's own rejection is bypassed or has a gap, the constraint will still block an invalid write, but it will surface as a generic database error rather than the specific message this slice's acceptance criteria require — the domain layer must be the one producing the user-facing rejection in the normal path.
- **Course code uniqueness is a cross-slice invariant.** A change to how course codes are validated or generated here has direct consequences for the equivalency slice (which keys equivalencies by course) and the modality slice (which keys modality assignments by course) — it cannot be loosened without checking both.
- **The active-student-count query's correctness depends on enrollment data this slice does not otherwise manage.** If a student's plan/level assignment can change through a path outside this slice, the count is only as accurate as that other path keeps it.
