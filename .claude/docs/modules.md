# Modules — Curricular Repository

Per-module functional detail for the four required capabilities (RC-01, RC-02, RC-02b, RC-03): domain entities, data shape, step-by-step flow, and the invariants that must hold for each.

**Implementation status as of this writing:** the codebase is still the stock `laravel/livewire-starter-kit` scaffold — Fortify auth (login, registration, 2FA, password reset) and Livewire Flux settings screens (`app/Livewire/Settings/*`) against a single `users` migration. None of the four modules below have domain code, migrations, or UI yet. Everything in this file describes the target design, not current state.

## Module dependency order

```
RC-01 (Study Plans)
   └─▶ RC-02 (Equivalencies)
          └─▶ RC-02b (Accreditation)

RC-03 (Modality Catalog) — independent, only shares the Course entity with RC-01
```

RC-02 cannot be modeled without RC-01's courses existing first. RC-02b cannot run without a valid RC-02 equivalency. RC-03 touches `Course` but has no dependency on the equivalency graph — it can be built in parallel.

---

## RC-01 — Study Plan Repository

### Purpose

Stores the structural definition of every study plan: its levels, the courses within each level, intra-plan prerequisites, and whether the plan is Active (Vigente) or Terminal.

### Domain entities & data shape

| Entity | Field | Type | Notes |
|---|---|---|---|
| `StudyPlan` | `program` | string | the academic program the plan belongs to |
| | `name` | string | |
| | `implementation_year` | int | |
| | `classification` | enum: `Active` \| `Terminal` | drives whether `enrollment_closing_date` is required |
| | `enrollment_closing_date` | date, nullable | **required when `classification = Terminal`**, forbidden/irrelevant otherwise |
| `Level` | `study_plan_id` | FK → StudyPlan | a plan has an ordered list of levels |
| | `order` / `name` | int / string | position within the plan |
| `Course` | `level_id` | FK → Level | a course belongs to exactly one level of exactly one plan |
| | `code` | string | unique within the plan |
| | `name` | string | |
| | `credits` | int | |
| `Prerequisite` | `required_course_id` | FK → Course | the course that must be passed first |
| | `dependent_course_id` | FK → Course | the course that requires it |

A `Prerequisite` is a directed edge `required_course → dependent_course`, scoped to a single plan — a prerequisite can never point to a course in a different plan.

### Flow

1. Director/Coordinator selects a program and starts a new plan: name, implementation year, classification.
2. If classification is `Terminal`, the enrollment closing date field becomes mandatory in the same form — this is a conditional-required validation, not a separate step.
3. Coordinator adds levels in order, and within each level adds courses (code, name, credits).
4. Coordinator registers prerequisites between courses already entered in the same plan.
5. Before save, the system validates that every `required_course_id` and `dependent_course_id` referenced by a prerequisite actually exists **within the same plan** — a prerequisite citing a course from another plan, or a course not yet saved, is rejected, not silently dropped.
6. On save, the full plan (levels → courses → prerequisites) persists together with its classification.
7. Viewing a plan renders the full structure plus classification; Terminal plans additionally show the enrollment closing date next to the classification badge.

### Invariants

- A Terminal plan without an `enrollment_closing_date` is invalid — block save, don't default it.
- A prerequisite referencing a course outside the plan is invalid — block save, don't warn-and-allow.
- Course codes are meaningful identifiers used later by RC-02 (as `source`/`target` of an equivalency) and RC-03 (as the entity a modality attaches to) — `Course` is the shared join point between all four modules.

### Domain vs. adapter split

Plan/Level/Course/Prerequisite are domain entities with one real invariant to enforce (prerequisite scoping + conditional-required date) — cheap enough to validate in the domain layer via a plain value check, no graph algorithm needed here (that complexity is RC-02's, not RC-01's). Laravel's role is CRUD-shaped: a Form Request/Livewire component collects the structure, an Eloquent-backed repository adapter persists it behind a domain-defined port.

---

## RC-02 — Cross-Plan Equivalency Registration with Integrity Validation

This is the centerpiece module — the equivalency graph and its two integrity guarantees (no cycles, no silent contradictions) are the hardest and highest-weighted part of the whole system.

### Purpose

Registers that a course from an old plan is equivalent to a course in a new plan, backed by a mandatory official resolution, and guarantees the resulting graph never contains a directed cycle or an unresolved contradiction.

### Domain entities & data shape

| Entity | Field | Type | Notes |
|---|---|---|---|
| `Equivalency` | `source_course_id` | FK → Course (old plan) | |
| | `target_course_id` | FK → Course (new plan) | |
| | `direction` | enum: `old_to_new` \| `new_to_old` \| `bidirectional` | see Direction semantics below |
| | `resolution_id` | FK → Resolution | **mandatory** — an equivalency without a resolution cannot exist |
| | `status` | enum: `Active` \| `Superseded` | set by contradiction resolution, never by deletion |
| `Resolution` | `number` | string | official resolution identifier |
| | `document` | file reference | the attached PDF — mandatory, no equivalency saves without it |

### Direction semantics (open design point)

`direction` states which way an equivalency is *applied* for accreditation (RC-02b): `old_to_new` means passing the source course accredits the target course; `new_to_old` is the mirror; `bidirectional` applies both ways. What direction implies for **cycle-detection edge orientation** is not specified by the requirements and needs an explicit domain-modeling decision before implementation — the two candidates are (a) `bidirectional` inserts two directed edges (source→target and target→source) into the same graph used for cycle detection, or (b) direction only governs accreditation and cycle detection always treats every registered pair as one directed edge matching its stated direction. Pick one and document the choice here once decided — don't leave it implicit in the code.

### Flow

1. User selects a source course (old plan) and a target course (new plan) — cross-plan by construction, RC-02 does not model same-plan equivalencies.
2. User selects direction and attaches the resolution document; a resolution number is entered.
3. **Cycle check runs first:** the system walks the existing equivalency graph (nodes = courses, directed edges = registered equivalencies) and checks whether adding this edge would close a directed cycle of any length.
   - Reject on cycle: response must include the exact conflicting chain (e.g. `A → B → C → A`), not just "cycle detected."
4. **Contradiction check runs second**, only if step 3 passed: the system checks whether an equivalency already exists for the same `(source_course, target_course, direction)` triple with a different resolution/outcome.
   - On contradiction: block the save, surface both conflicting resolutions, and require an Admin/Coordinator to explicitly designate which one prevails. The losing resolution's equivalency is marked `Superseded` — **never deleted, never silently overwritten.**
5. If neither check fails, persist the equivalency as `Active`.

### Cycle detection — implementation note

Model courses as graph nodes and equivalencies as directed edges explicitly; implement DFS-based cycle detection tracking the recursion stack, so cycles of any length are caught — not just simple 2–3 node loops. This lives in the domain layer as a pure function/service (`graph in → cycle found: bool, chain: Course[]`), independent of Eloquent, so it's unit-testable without a database and without Laravel booted at all.

### Contradiction detection — implementation note

Keying on `(source_course_id, target_course_id, direction)` is the mechanism described by the requirements; "different outcome" for the same triple is what triggers the conflict. This, too, belongs in the domain layer as a pure check against the in-memory/queried set of existing equivalencies for that triple, before any write happens.

### Invariants

- No equivalency without an attached resolution document — enforced at the boundary (Form Request/domain factory), not just a UI hint.
- No directed cycle in the equivalency graph, regardless of chain length.
- No two `Active` equivalencies with contradictory outcomes for the same `(source, target, direction)` triple — a losing one must be explicitly marked `Superseded` by a human decision, never auto-resolved.
- Historical equivalencies (including `Superseded` ones) are never deleted — RC-02b and audit/versioning depend on that history remaining queryable.

### Domain vs. adapter split

Cycle detection and contradiction detection are the two domain services with real algorithmic weight in this system — they must not import Eloquent or Livewire. The port they depend on (e.g. `EquivalencyRepository`) is implemented by an Eloquent-backed adapter that loads the current graph edges. The Livewire form is purely an adapter collecting input and rendering the domain's rejection (cycle chain / contradiction pair) or success.

---

## RC-02b — Informational Accreditation via Equivalency

### Purpose

Once an equivalency is validly registered (RC-02), automatically mark the target course as accredited for any test student who already passed the source course — strictly in the registered direction, and only informational (this does not touch or replace any official student transcript system).

### Domain entities & data shape

| Entity | Field | Type | Notes |
|---|---|---|---|
| `StudentAcademicRecord` (simplified/test) | `student_id` | identifier | test/simulated students only |
| | `course_id` | FK → Course | |
| | `status` | enum: `Passed` | only the passed state matters for accreditation |
| `AccreditationRecord` | `student_academic_record_id` | FK → StudentAcademicRecord | the accredited entry created |
| | `target_course_id` | FK → Course | the course being marked accredited |
| | `equivalency_id` | FK → Equivalency | source of truth for the accreditation |
| | `label` | string | rendered as `"Accredited by equivalency — Resolution [number]"` |

### Flow

1. Triggered when an `Equivalency` is saved as `Active` (RC-02, final step).
2. System searches `StudentAcademicRecord` for test students with `course_id = equivalency.source_course_id` and `status = Passed`.
3. For each match, create an `AccreditationRecord` marking `equivalency.target_course_id` as accredited, labeled with the resolution number.
4. Direction is enforced here, not re-validated: only students holding the source course in the equivalency's registered direction get accredited. An `old_to_new` equivalency never accredits in the `new_to_old` sense, and vice versa; only `bidirectional` accredits both ways.
5. Student's internal history view reflects the new accredited entry immediately.

### Invariants

- Never accredit in the reverse of what the resolution approved — this is explicitly called out as a required negative test case (a plan saved `old→new` must **not** accredit the reverse case).
- Accreditation is derived, not independently editable — it exists only as a consequence of an `Active` equivalency; if an equivalency is superseded, the accreditation's continued validity is a downstream question this module does not yet resolve (flag if requirements clarify this later).

### Domain vs. adapter split

The direction-matching rule ("does this equivalency's direction cover this student's passed course?") is a small pure domain function reusable outside of Eloquent. The bulk lookup/write of `StudentAcademicRecord` → `AccreditationRecord` is adapter work (Eloquent queries), triggered by a domain event or explicit call after RC-02's save succeeds — this module is a consumer of RC-02, not a parallel entry point.

---

## RC-03 — Modality Catalog and Modality Resolutions

### Purpose

Maintains the catalog of teaching modalities and enforces that a course can only be assigned a modality flagged "requires resolution" when a currently-valid resolution exists for that specific course.

### Domain entities & data shape

| Entity | Field | Type | Notes |
|---|---|---|---|
| `Modality` | `name` | string | seed values: Presencial (In-person), Híbrido (Hybrid), Virtual, Tutoría (Tutoring), Aprendizaje Remoto (Remote Learning) |
| | `requires_resolution` | bool | admin-maintained flag |
| `CourseModality` | `course_id` | FK → Course | |
| | `modality_id` | FK → Modality | |
| | `resolution_id` | FK → Resolution, nullable | required only if `modality.requires_resolution = true` |
| | `valid_from` / `valid_to` | date | resolution validity window |

### Flow

1. Admin maintains the `Modality` catalog: name + `requires_resolution` flag. Seed data ships with the five listed modalities; In-person (Presencial) is the system default.
2. Every newly registered course with no modality specified defaults to `Presencial`.
3. When assigning/changing a course's modality: system checks `modality.requires_resolution`.
   - If `false` → apply immediately, no resolution needed.
   - If `true` → require a resolution (document, approving body, validity dates) currently within its validity window for that specific course; if none exists, reject with `"No valid modality resolution exists for this course"`.
4. On a valid resolution, the modality is applied and visible on the course.

### Invariants

- A modality flagged `requires_resolution` can never be applied without a currently-valid resolution on file for that course — no override path.
- Default modality for a course with none specified is always `Presencial`, never null/unset.

### Domain vs. adapter split

The rule "modality X requires a valid resolution before assignment" is a one-line domain check (`modality.requiresResolution() && !hasValidResolution(course, modality)` → reject) — small enough that the main design question is where the `Resolution` entity is shared between RC-02 and RC-03 (both use "an official document backing a decision"). Treat `Resolution` as one domain concept referenced by both `Equivalency` and `CourseModality`, not two separate types, to avoid duplicating the same validity concept.

---

## Shared concepts across modules

- **`Course`** is the join point for all four modules: RC-01 defines it, RC-02 links pairs of it across plans, RC-02b accredits it, RC-03 attaches a modality to it.
- **`Resolution`** is used by both RC-02 (equivalency) and RC-03 (modality) as "the official document that authorizes this decision" — same shape (document, number, validity), different owning relationship. Model it once.
- **Versioning/non-destruction** is a cross-cutting requirement, not just an RC-02 rule: superseded equivalencies, historical accreditation, and expired modality resolutions must all remain queryable, never hard-deleted.
