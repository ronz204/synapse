# Modules — Curricular Repository

Per-module functional detail for the four required capabilities (RC-01, RC-02, RC-02b, RC-03): domain entities, data shape, step-by-step flow, and the invariants that must hold for each.

**Implementation status as of this writing:** the persistence layer (migrations, Eloquent models, enums, factories) for all four modules already exists. What's still missing across all four is the framework-free domain layer (entities, repository contracts, domain services), the application layer (use cases), and the presentation layer (Livewire CRUD screens, policies, routes) — today the only working end-to-end bounded-context slice is identity/access (roles and permissions), which serves as the structural reference for how the four modules below should be built. Everything in this file describes the target design; check the per-module sections below for notes on where the current schema already diverges from that design.

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

Stores the structural definition of every study plan: its levels, the courses within each level, intra-plan prerequisites, and whether the plan is Active or Terminal.

### Domain entities & data shape

| Entity | Field | Type | Notes |
|---|---|---|---|
| `Program` | `name` | string | a course's owning program; a course flagged as a shared "service" course has no program |
| | `active` | bool | |
| `StudyPlan` | `program` | FK → Program | the academic program the plan belongs to |
| | `name` | string | |
| | `implementation_year` | int | |
| | `classification` | enum: `Active` \| `Terminal` | drives whether `enrollment_closing_date` is required |
| | `enrollment_closing_date` | date, nullable | **required when `classification = Terminal`**, forbidden/irrelevant otherwise |
| `Level` | `study_plan_id` | FK → StudyPlan | a plan has an ordered list of levels |
| | `number` | int | position within the plan; unique per plan, no separate name field |
| `Course` | `program_id` | FK → Program, nullable | a course belongs to a **program**, not to a single level/plan — it is intentionally reusable across multiple plans/levels; nullable only for shared "service" courses |
| | `code` | string | unique **across the whole system**, not just within a plan — this is the identifier the equivalency and modality modules key off of |
| | `name` | string | |
| | `is_service`, `is_bottleneck`, `requires_laboratory`, `laboratory_type`, `active` | bool / bool / bool / enum / bool | course attributes beyond the minimal set; `laboratory_type` only meaningful when `requires_laboratory` |
| `CourseLevel` (pivot) | `level_id`, `course_id` | FK → Level, FK → Course | the many-to-many link between a course and the levels/plans it's used in |
| | `credits` | int | **lives on this pivot, not on `Course`** — the same course can be worth a different number of credits in different levels/plans |
| `Prerequisite` | `study_plan_id` | FK → StudyPlan | stored directly against a plan, not derived transitively through course → level → plan |
| | `required_course_id` | FK → Course | the course that must be passed first |
| | `dependent_course_id` | FK → Course | the course that requires it |

A `Prerequisite` is a directed edge `required_course → dependent_course`, scoped to a single plan. Because `Course` is no longer level/plan-exclusive, "scoped to a single plan" means both courses must be **linked** (via `CourseLevel`) to some level of that plan — not that the courses themselves belong to it.

### Flow

1. Director/Coordinator selects a program and starts a new plan: name, implementation year, classification.
2. If classification is `Terminal`, the enrollment closing date field becomes mandatory in the same form — this is a conditional-required validation, not a separate step.
3. Coordinator adds levels in order. Courses are drawn from the program's course catalog (created there if new) and linked into a level with a credits value specific to that link — a course is not "added" as a plan-exclusive record.
4. Coordinator registers prerequisites between courses already linked to a level of the same plan.
5. Before save, the system validates that every `required_course_id` and `dependent_course_id` referenced by a prerequisite is actually **linked to some level of that same plan** — a prerequisite citing a course not linked to the plan, or a course not yet saved, is rejected, not silently dropped.
6. On save, the full plan (levels → linked courses with their per-link credits → prerequisites) persists together with its classification.
7. Viewing a plan renders the full structure plus classification; Terminal plans additionally show the enrollment closing date next to the classification badge.

### Invariants

- A Terminal plan without an `enrollment_closing_date` is invalid — block save, don't default it.
- A prerequisite referencing a course not linked to the plan is invalid — block save, don't warn-and-allow.
- A prerequisite's required and dependent course must be distinct.
- Course codes are unique across the whole system and are meaningful identifiers used later by RC-02 (as `source`/`target` of an equivalency) and RC-03 (as the entity a modality attaches to) — `Course` is the shared join point between all four modules, which is why it is modeled as its own aggregate rather than nested inside `StudyPlan`.
- A course's credits value is a property of its link to a level, not of the course itself — the same course can be worth different credits in different plans/levels.
- A non-service course must belong to a program; a service course must not.

### Domain vs. adapter split

`Course` is modeled as its own aggregate (reused across plans, and the entity RC-02/RC-03 reference), independent of `StudyPlan`. `StudyPlan` is a separate aggregate that owns `Level` and `Prerequisite` as child entities within its own boundary and references `Course` only by ID. Both aggregates have real invariants to enforce (prerequisite scoping, conditional-required date, course-code uniqueness, service/program consistency) — cheap enough to validate in the domain layer via plain value checks, no graph algorithm needed here (that complexity is RC-02's, not RC-01's). Laravel's role is CRUD-shaped: a Livewire component collects the structure, an Eloquent-backed repository adapter persists it behind a domain-defined port per aggregate.

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
| | `direction` | enum: `OldToNew` \| `NewToOld` \| `Bidirectional` | see Direction semantics below |
| | `resolution_number` | string | plain attribute of the equivalency — there is no separate, dedicated resolution entity; the same number may cover more than one course pair (one resolution document, several equivalencies) but never the identical pair twice |
| | (attached document) | polymorphic document reference | **mandatory** — an equivalency without an attached resolution document cannot be saved. Carried by this project's general-purpose document-attachment mechanism, the same one used elsewhere for other kinds of attachments, not a bespoke one for equivalencies |
| | `status` | enum: `Active` \| `Superseded` | set by contradiction resolution, never by deletion |
| | `superseded_by` | self-reference, nullable | when `Superseded`, points at whichever equivalency prevailed over it |

### Direction semantics (resolved)

`direction` states which way an equivalency is *applied* for accreditation (RC-02b): `OldToNew` means passing the source course accredits the target course; `NewToOld` is the mirror; `Bidirectional` applies both ways. This also settles cycle-detection edge orientation: an edge always follows the accreditation-flow meaning of `direction`, not a fixed source→target reading. `OldToNew` produces one directed edge source→target. `NewToOld` produces one directed edge **target→source** — reversed relative to how the pair is stored, because it's passing the target course that accredits the source course in that case. `Bidirectional` produces both directed edges. Only `Active` equivalencies contribute edges to the graph used for cycle detection — a `Superseded` one is dead and must never be able to cause a phantom cycle rejection.

### Flow

1. User selects a source course (old plan) and a target course (new plan) — cross-plan by construction, RC-02 does not model same-plan equivalencies.
2. User selects direction and attaches the resolution document; a resolution number is entered. Attaching the document is validated at this input boundary — before cycle/contradiction checks run, not as part of either graph check.
3. **Cycle check runs next:** the system walks the graph of currently-`Active` equivalencies (nodes = courses, directed edges oriented per the resolved rule above) and checks whether adding this edge would close a directed cycle of any length.
   - Reject on cycle: response must include the exact conflicting chain (e.g. `A → B → C → A`), not just "cycle detected."
4. **Contradiction check runs after**, only if step 3 passed: the system checks whether an `Active` equivalency already exists for the same `(source_course, target_course, direction)` triple. Because an equivalency record *is* that triple (there is no separate field representing an independent "outcome"), any second submission naming an already-active triple is treated as a contradiction — there is no case where a second submission for the same triple is silently allowed or auto-merged.
   - On contradiction: block the save, surface both conflicting resolutions, and require an Admin/Coordinator to explicitly designate which one prevails. Whichever one loses — the new submission or the previously-active one — is persisted with `status = Superseded` and `superseded_by` pointing at the winner; the loser is **never discarded unsaved, never deleted, never silently overwritten.**
5. If neither check fails, persist the equivalency as `Active`.

### Cycle detection — implementation note

Model courses as graph nodes and equivalencies as directed edges explicitly, oriented per the resolved Direction semantics above; implement DFS-based cycle detection tracking the recursion stack, so cycles of any length are caught — not just simple 2–3 node loops. This lives in the domain layer as a pure function/service (`graph in → cycle found: bool, chain: Course[]`), independent of Eloquent, so it's unit-testable without a database and without Laravel booted at all. The edge set must be loaded fresh for each check, never cached across requests, or a concurrent registration's cycle can be missed.

### Contradiction detection — implementation note

Keying on `(source_course_id, target_course_id, direction)` is the mechanism described by the requirements; because the data model has no way to represent an equivalency's outcome independently of that triple, any second `Active`-conflicting submission for it is the trigger — genuinely conflicting resolutions and the same fact resubmitted twice are indistinguishable under the current model and both hit the same human-decision path. This, too, belongs in the domain layer as a pure check against the currently-queried set of `Active` equivalencies for that triple, before any write happens.

### Invariants

- No equivalency without an attached resolution document — enforced at the input boundary, not just a UI hint.
- No directed cycle in the equivalency graph (edges oriented per the resolved Direction semantics, `Active` equivalencies only), regardless of chain length.
- No two `Active` equivalencies for the same `(source, target, direction)` triple — a losing one must be explicitly marked `Superseded` by a human decision, never auto-resolved, and always persisted rather than discarded.
- Historical equivalencies (including `Superseded` ones) are never deleted — RC-02b and audit/versioning depend on that history remaining queryable.

### Domain vs. adapter split

Cycle detection and contradiction detection are the two domain services with real algorithmic weight in this system — they must not import Eloquent or Livewire. The port they depend on (e.g. `EquivalencyRepository`) is implemented by an Eloquent-backed adapter that loads the current graph edges. The Livewire form is purely an adapter collecting input and rendering the domain's rejection (cycle chain / contradiction pair) or success.

---

## RC-02b — Informational Accreditation via Equivalency

### Purpose

For any course pair connected by a currently-active equivalency, automatically mark a student as accredited for the course the equivalency's direction makes them eligible for, the moment they qualify — strictly in the registered direction, and only informational (this does not touch or replace any official student transcript system). This is a standing rule, not a one-time action taken only when the equivalency is first registered: it also applies later, whenever a student comes to qualify under an equivalency that was already active.

### Domain entities & data shape

There is no separate accreditation entity. Accreditation is a `StudentAcademicRecord` row like any other, written for the target course, distinguished only by its status and its equivalency reference:

| Entity | Field | Type | Notes |
|---|---|---|---|
| `StudentAcademicRecord` | `student_id` | FK → Student | every `Student` row in this codebase is, by construction, part of the informational system this requirement describes — there is no separate "test student" marker distinguishing them from a real one, because no other student-record system exists alongside it |
| | `course_id` | FK → Course | the course this record is about — the *target* course, for an accreditation-by-equivalency record |
| | `status` | enum, includes an equivalency-accreditation case among others (passed, failed, accredited by equivalency, accredited by another kind of validation, a waived prerequisite) | an accreditation record is simply a record with the equivalency-accreditation status |
| | `equivalency_id` | FK → Equivalency, nullable | set only for an equivalency-accreditation record, pointing at whichever equivalency granted it; excluded from ordinary mass-assignment so nothing outside this flow can set it |

The label shown to a user ("Accredited by equivalency — Resolution [number]") is produced at display time by following `equivalency_id` through to that equivalency's resolution number — it is not a stored value.

### Flow

1. Two triggers, converging on the same underlying rule: an equivalency newly becoming `Active`, and a student's academic record for some course newly transitioning to `Passed`. Both raise a signal the rest of the system reacts to — this module never polls for either.
2. On either trigger, the underlying rule is: given a course the student has passed, and the graph of directed edges formed by all currently-`Active` equivalencies (the same graph RC-02's cycle detection walks, oriented the same way — see RC-02's Direction semantics), accredit whatever course the edge points to. An `OldToNew` equivalency's edge points from source to target: passing the source qualifies for the target. A `NewToOld` equivalency's edge points the other way: passing the target qualifies for the source. A `Bidirectional` equivalency produces both edges, qualifying either way. This module does not re-derive direction logic independently — it reads the same edge orientation RC-02 already resolves.
3. Before writing a new accreditation record: skip if the student already holds any record for the target course that already means it's satisfied (passed directly, already accredited by equivalency, or already accredited by another kind of validation); skip if an accreditation record for that exact student/course/equivalency combination already exists.
4. Otherwise, write a new `StudentAcademicRecord` for the student on the target course, with the equivalency-accreditation status and `equivalency_id` set to the granting equivalency.
5. Student's internal history view reflects the new accredited entry immediately.

### Invariants

- Never accredit in the reverse of what the resolution approved — this is explicitly called out as a required negative test case (an equivalency saved `OldToNew` must **not** accredit via the `NewToOld` reading of the graph).
- No duplicate accreditation record for the same student/target-course/equivalency combination, and no redundant accreditation when the student already satisfies the target course some other way.
- Accreditation is derived, never independently editable — it exists only as a consequence of an `Active` equivalency plus a qualifying passed record.
- An equivalency transitioning to `Superseded` does not retroactively revoke accreditation records already granted while it was active — those stay as a historical fact — and it stops being eligible to grant any new ones going forward. A read path displaying an accreditation's resolution must not assume the referenced equivalency is still active.

### Domain vs. adapter split

The direction-matching rule ("given a passed course and the active-equivalency graph, what does it qualify for") is a small pure domain function shared with, not duplicated from, RC-02's own edge-orientation logic. The bulk lookup/write against `StudentAcademicRecord` is adapter work (Eloquent queries), invoked from two listeners — one reacting to an equivalency becoming `Active`, one reacting to a student record becoming `Passed` — both calling the same core operation rather than diverging into separate logic. This module is a consumer of RC-02's data and rules, not a parallel entry point that reimplements them.

---

## RC-03 — Modality Catalog and Modality Resolutions

### Purpose

Maintains the catalog of teaching modalities and enforces that a course can only be assigned a modality flagged "requires resolution" when a currently-valid resolution exists for that specific course.

### Domain entities & data shape

There is no separate `CourseModality` join entity. A course's currently-assigned modality is a direct reference on `Course` itself (see RC-01); `ModalityResolution` is the backing-evidence record checked at assignment time, not the assignment relationship itself:

| Entity | Field | Type | Notes |
|---|---|---|---|
| `Modality` | `name` | string | seed values: Presencial (In-person), Híbrido (Hybrid), Virtual, Tutoría (Tutoring), Aprendizaje Remoto (Remote Learning) |
| | `requires_resolution` | bool | admin-maintained flag |
| `ModalityResolution` | `course_id` | FK → Course | |
| | `modality_id` | FK → Modality | |
| | `resolution_number` | string | plain attribute directly on this record — there is no separate, dedicated resolution entity shared with RC-02 |
| | (attached document) | polymorphic document reference | **mandatory**, via the same general-purpose document-attachment mechanism RC-02 uses — not yet wired up on this record as of this writing, unlike RC-02's |
| | `approving_body` | string | |
| | `valid_from` / `valid_to` | date, `valid_to` nullable | resolution validity window; a query scope already implements the "currently valid" check (started, and not yet ended) |

### Flow

1. Admin maintains the `Modality` catalog: name + `requires_resolution` flag. Seed data ships with the five listed modalities; In-person (Presencial) is the system default.
2. Every newly registered course with no modality specified defaults to `Presencial` — an explicit application rule, not an incidental default.
3. When assigning/changing a course's modality: system checks `modality.requires_resolution`.
   - If `false` → apply immediately, no resolution needed.
   - If `true` → require a currently-valid `ModalityResolution` (document, approving body, validity dates) on file for that specific course and modality; if none exists, reject with `"No valid modality resolution exists for this course"`.
4. On a valid resolution, the course's modality reference is updated and visible on the course.

This is a write-time gate only: once applied, a course's modality is not automatically revisited later if its backing resolution's validity window lapses, or if the catalog's `requires_resolution` flag changes afterward.

### Invariants

- A modality flagged `requires_resolution` can never be applied without a currently-valid, document-backed resolution on file for that course and modality — no override path.
- A `ModalityResolution` cannot be saved without an attached document.
- Default modality for a course with none specified is always `Presencial`, never null/unset.
- A modality currently referenced by any course cannot be deleted from the catalog.

### Domain vs. adapter split

The rule "modality X requires a valid resolution before assignment" is a small, pure domain check reusing the same validity-window logic the persistence layer already implements as a query scope, expressed as a value check rather than a database query at the point it's evaluated. There is no `Resolution` entity shared with RC-02 — each module independently stores its own resolution number as a plain attribute on the record it belongs to, backed separately by the same shared document-attachment mechanism for the file itself.

---

## Shared concepts across modules

- **`Course`** is the join point for all four modules: RC-01 defines it, RC-02 links pairs of it across plans, RC-02b accredits it, RC-03 attaches a modality to it.
- **"An official document that authorizes a decision"** is a recurring shape both RC-02 (equivalency) and RC-03 (modality) need, but neither implements it as a shared, dedicated entity — confirmed for both. Each stores its own resolution number as a plain attribute on the record it belongs to (RC-03's additionally carries an approving body and a validity window that RC-02's does not), separately backed by the project's general-purpose document-attachment mechanism for the file itself. RC-02's use of that mechanism is wired; RC-03's is not yet.
- **Versioning/non-destruction** is a cross-cutting requirement, not just an RC-02 rule: superseded equivalencies, historical accreditation, and expired modality resolutions must all remain queryable, never hard-deleted.
