# Approach — Curricular Repository Module

## What this project actually is

You're building a system that replaces institutional tribal knowledge. Right now, when a Program Director leaves, everything they know about course equivalencies between study plans, modality resolutions, and terminal plans disappears with them — it lives in someone's head, personal folders, or scattered emails. The system needs to make that knowledge centralized, versioned, and traceable instead.

This is **not a decorative CRUD app**. The core of the system is a graph of course equivalencies between study plans. An equivalency between two courses from different plans determines whether a student can skip a course they've already passed under an old plan. Get this wrong, and a real student loses a semester.

**The hardest part of this project isn't storing data — it's guaranteeing two invariants:**
1. The equivalency graph must **never contain a cycle** (A equivalent to B, B to C, C back to A).
2. Two resolutions must **never be left silently contradictory** — a conflict must be caught and require an explicit human decision about which resolution prevails.

Everything else (forms, tables, file uploads) is comparatively easy. Design and test the graph-integrity logic first and hardest.

---

## Functional Requirements

Each requirement below has explicit **acceptance criteria**. A requirement that doesn't satisfy its acceptance criteria counts as **not implemented**, even if the code technically exists and "kind of works." Build the acceptance criteria as your test cases from day one.

### RC-01 — Study Plan Repository (Priority: High)

**What it does:** Stores every study plan for every program: its levels, the courses within each level, prerequisite relationships between courses in the same plan, and the year it was implemented. Each plan is classified as **"Active"** or **"Terminal"**. Must support querying how many test students are active per plan/level.

**Inputs:**
- Program, plan name, implementation year, classification (Active / Terminal).
- List of levels; each level has a list of courses (code, name, credits).
- Prerequisites between courses *within the same plan* (required course → course that requires it).
- For Terminal plans only: enrollment closing date (**required field, only for Terminal**).

**Flow:**
1. Director/Coordinator picks a program and creates a new plan.
2. Registers levels, and within each level, courses with their prerequisites.
3. System validates that every course cited as a prerequisite actually exists **within the same plan** before allowing save.
4. System persists the plan with its Active/Terminal classification.

**Outputs:**
- Full plan structure view (levels → courses → prerequisites).
- Active/Terminal classification visible.
- If Terminal: enrollment closing date shown next to the classification.

**Acceptance criteria (= your test cases):**
- Selecting a plan displays the full structure (levels, courses, prerequisites) + classification.
- Terminal plans additionally show the enrollment closing date.
- A course referenced as a prerequisite that doesn't exist in the plan **cannot be saved** — this must be blocked, not just warned.

---

### RC-02 — Cross-Plan Equivalency Registration with Integrity Validation (Priority: High)

**This is the centerpiece requirement of the whole project.**

**What it does:** Registers equivalency records between plans: source course (old plan), target course (new plan), the **direction** the equivalency applies (old→new / new→old / bidirectional), an official resolution number, and an attached resolution document. **An equivalency without an attached document cannot be saved — no exceptions.**

The system must validate the graph:
- **(a) Cycle detection:** reject any new equivalency that would form a directed cycle with existing ones — regardless of cycle length (not just 2-3 node cycles; must handle longer/complex chains).
- **(b) Contradiction detection:** if two resolutions define contradictory outcomes for the same (source course, target course, direction) triple, block the save and require a human to explicitly mark which resolution prevails. The losing resolution gets tagged **"Superseded"**.

**Inputs:**
- Source course (old plan), target course (new plan).
- Direction (old→new / new→old / bidirectional).
- Official resolution number + resolution PDF.

**Flow:**
1. User selects source and target courses from two different plans.
2. Selects direction and attaches the resolution PDF.
3. System walks the existing equivalency graph and checks whether adding this edge would create a directed cycle.
4. If it would → **reject the save**, and show the exact conflicting chain (e.g., "A → B → C → A").
5. If no cycle → check whether an equivalency already exists for the same (source, target, direction) triple with a **different** outcome.
6. If contradictory → **block the save**, require an Admin/Coordinator to designate which resolution prevails; the other becomes "Superseded."
7. If neither issue applies → save the equivalency.

**Outputs:**
- Persisted equivalency showing direction, resolution number, and attached document.
- Specific error messages: cycle detected (with full chain) or contradiction detected (with both conflicting resolutions shown).

**Acceptance criteria (= your test cases):**
- Attempt to save without a document → blocked with the specific message: *"You must attach the resolution that approves this equivalency."*
- Save with direction "old plan → new plan" → the record correctly displays that direction.
- New equivalency that would close a directed cycle with existing ones → rejected, showing the exact conflicting chain.
- Second contradictory record for the same pair+direction → blocked, requiring the prevailing resolution to be designated.

**Implementation note:** This is fundamentally a graph problem. Model it explicitly (nodes = courses, directed edges = equivalencies) and implement real cycle detection (e.g., DFS-based, tracking the recursion stack) — don't hardcode checks for small/simple cases. The rubric explicitly penalizes solutions that only catch 2-3 node cycles but fail on longer chains.

---

### RC-02b — Informational Accreditation via Equivalency (Priority: High)

**What it does:** Once an equivalency is validly registered, the system applies an *informational* accreditation to a simplified internal academic record for test students — strictly in the registered direction. This is explicitly informational for the purposes of this project; it does not replace any official student record.

**Inputs:**
- List of test students with the source course marked as passed in their simplified record.
- A valid, already-registered equivalency (from RC-02).

**Flow:**
1. When a valid equivalency is saved, the system searches the internal history for students who have the source course marked as passed.
2. For each one, marks the target course as **"Accredited by equivalency — Resolution [number]"** — only in the registered direction.
3. The system must **never** accredit in the reverse direction from what the resolution approved.

**Outputs:**
- Updated student internal history showing the target course as accredited, with the resolution number.

**Acceptance criteria:**
- Equivalency saved with direction "old → new" → students who passed the old course now show the new course as "Accredited by equivalency" in their history.
- No student in the new plan gets accreditation for the old course when the direction doesn't permit it — **test the reverse-direction case explicitly.**

---

### RC-03 — Modality Catalog and Modality Resolutions (Priority: High)

**What it does:** Manages the master catalog of teaching modalities (seed values: Presencial/In-person, Híbrido/Hybrid, Virtual, Tutoría/Tutoring, Aprendizaje Remoto/Remote Learning). Each modality is flagged as **"requires resolution"** or not. Stores the resolutions that approve a course's modality (attached document, approving body, validity dates). Default modality for any new course is **In-person (Presencial)**. A course **cannot** be registered with a modality flagged "requires resolution" without a currently-valid resolution on file.

**Inputs:**
- Modality catalog: name + "requires resolution" flag (yes/no).
- To assign a modality to a course: course, modality, resolution document (if applicable), validity dates.

**Flow:**
1. Admin maintains the modality catalog with its "requires resolution" flag.
2. When registering/modifying a course's modality, system checks whether the chosen modality requires a resolution.
3. If it does and no valid resolution exists for that course → reject the operation.
4. If a valid resolution exists → apply the modality to the course.

**Outputs:**
- Updated, visible course modality.
- Specific rejection message: *"No valid modality resolution exists for this course"* when applicable.

**Acceptance criteria:**
- Attempt to register a course with Hybrid modality without a valid resolution → rejected with the specified message.
- Every new course with no modality specified defaults to In-person.

---

## Mandatory Technical Stack & Architecture

These are non-negotiable, evaluated as part of "technical integration" and apply regardless of team creativity:

- **Full TALL stack:** Tailwind CSS + Alpine.js + Laravel + Livewire.
- **TypeScript** — must actually be used (not just present in package.json).
- **At least one external REST API** consumed by the system.
- **JWT authentication.**
- **Environment variables** for configuration (no hardcoded secrets/config).
- **Basic unit tests.**
- **Documented Git repository:** readable commit history + README with installation instructions.

### Architecture: Hexagonal (Ports & Adapters) + DDD — this is graded explicitly

- All domain logic must be organized under Hexagonal Architecture and Domain-Driven Design.
- **Hard constraint:** the domain package **must not import Laravel, Livewire, or Alpine.js classes.**
- **Self-test:** if you delete the framework and the domain layer stops compiling, the architecture is wrong. This is literally how it will be evaluated — expect this to be checked directly.
- Practically: keep the equivalency graph, cycle detection, contradiction resolution, and modality rules as pure domain logic (entities, value objects, domain services) behind ports, with Laravel/Livewire as adapters calling into that domain — not the other way around.

---

## Team & Process Constraints

- Teams of **max 3 people**. No solo projects, no teams larger than 3.
- **Three mandatory progress checkpoints** during development. These have no grade of their own but are a **hard gate**: without all three approved, the team cannot present the final defense at all.
- Build incrementally and verifiably — not a last-minute assembly job. The checkpoints exist specifically to prevent that.

---

## The AI Decision Diary (10% of final grade)

A living document, updated throughout development, that must record:
- What was actually asked of the AI.
- What was accepted from its response, and what wasn't.
- What had to be corrected because it was wrong or incomplete.
- What was learned from the process.

**Key point:** using AI is explicitly allowed and expected — this diary is evidence that *your* technical judgment, not the tool, drove the decisions. A generic entry like "AI helped with the code" **fails this requirement outright**. Every entry needs to be specific and verifiable against the actual code/decisions in the project.

Practical implication: keep this diary as you go, not retroactively. Log real prompts, real AI mistakes you caught (there should be at least one concrete, verifiable case), and genuine specific learnings — not vague reflections.

---

## Oral Defense — what to actually prepare for

- The evaluator can point at **any** function/component and ask for a live explanation.
- The evaluator can request a **live minor modification** (add a field, change a validation, handle a new error code) and watch how the team handles it.
- The evaluator can question anything logged in the AI diary.
- **Anticipating what a change will break, before running it, is weighted as the clearest signal of real understanding** — more than the code itself.
- **Any team member, regardless of who is asked, must be able to explain the system as a whole**, not just their own part. Distributed/shared understanding is explicitly graded — don't silo work so tightly that only one person understands the equivalency graph logic, for example.

---

## What gets evaluated (for your own prioritization)

Three rubrics, 100 points each, 5 criteria of 20 pts each (Excellent / Regular / Insufficient):

**1. Functionality (15% of final grade)**
- Completeness of RC-01/RC-02/RC-02b/RC-03 — all fields, flows, outputs, no shortcuts or hardcoded data.
- **Graph integrity validation (RC-02)** — this is its own dedicated 20-point line item. Detecting only short cycles or handling contradictions partially caps you at "Regular." Missing cycle detection entirely, or allowing contradictory saves, is "Insufficient."
- Specific, correct error messages matching the exact spec wording/conditions.
- Versioning/traceability: historical data must never be overwritten; accreditation only in the approved direction.
- Full technical stack integration, all functional and verifiable, domain not depending on framework classes.

**2. Oral Defense (15% of final grade)**
- Explaining architecture/design decisions without needing to read from notes or code.
- Justifying business rules including edge cases from the acceptance criteria — not just "it works."
- Correctly implementing a live requested change without breaking existing functionality.
- Anticipating the blast radius of a change *before* running it.
- Every team member being able to explain the whole system, not just their own piece.

**3. AI Decision Diary (10% of final grade)**
- Specific, contextualized records of what was asked of the AI.
- Concrete accept/reject reasoning per consultation (not "it worked" / "I didn't like it").
- At least one real, verifiable case where the AI was wrong and how it was caught/fixed.
- Specific personal learning reflections, not generic statements.
- Diary content must be verifiable against what was actually built — dates, components, and decisions must match reality.

---

## Suggested build order

1. **Domain model first, framework-free.** Model Plan, Level, Course, Prerequisite, Equivalency, Resolution, Modality as pure domain objects. Write the cycle-detection and contradiction-detection logic here, with unit tests, before touching Laravel at all.
2. **RC-01** (plans/courses/prerequisites) — establishes the data foundation everything else depends on.
3. **RC-02** (equivalencies + graph integrity) — the hardest and highest-weighted piece; get cycle detection and contradiction detection rock solid with tests covering long chains, not just toy 2-3 node cases.
4. **RC-02b** (accreditation) — depends directly on RC-02 being correct.
5. **RC-03** (modality catalog + resolutions) — largely independent, can be built in parallel by a second team member.
6. **Cross-cutting:** JWT auth, external REST API integration, TypeScript usage, env config, tests — weave these in throughout rather than bolting them on at the end, since "everything functional and verifiable" is explicitly what's graded.
7. **Diary discipline from day one** — log AI interactions as they happen; reconstructing this at the end reads as generic and fails the rubric.