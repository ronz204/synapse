# Equivalency Accreditation — Spec

## Objective

For any course pair connected by a currently-active equivalency, a student who has passed the course on the side the equivalency's direction makes eligible automatically gets an accreditation on the other course, citing the resolution that authorized it — purely informational, internal to this system, never touching or replacing an official transcript. This is a standing rule kept true at all times, not a one-time action performed only at the moment an equivalency is registered: it holds both when an equivalency newly becomes active with students already qualifying, and later, whenever a student comes to qualify under an equivalency that was already active.

## Scope

**In scope:**
- Sweeping existing passed-course records for newly-qualifying students the moment an equivalency becomes active.
- Accrediting a student going forward, the moment their record for a course transitions to passed, if that course is already a qualifying source under some active equivalency.
- Respecting the equivalency's direction exactly — accrediting only the course(s) that direction actually permits, never the reverse, and both ways for a bidirectional equivalency.
- Guarding against duplicate accreditation and against accrediting a course a student has already satisfied by some other means.

**Out of scope / deferred:**
- Any official or legal transcript integration — this system's student records are explicitly informational only, never a substitute for an institution's real academic record.
- The modality catalog — no dependency in either direction.
- Automatically revoking or altering an accreditation already granted once the equivalency that granted it is later superseded — treated as a historical fact, not a live status; see Invariants.
- Any student-history reporting surface beyond showing the accredited status (and the resolution that authorized it) on a student's own record view — a broader dashboard isn't implied by the requirement and isn't assumed here.

## Technical context

**Already built (persistence layer):** student records and their enrollment in a study plan, and a per-course academic record per student carrying a status (passed, failed, accredited by equivalency, accredited by another kind of validation, or a waived prerequisite), an optional grade, and an optional reference back to the equivalency that produced it when the status is an equivalency-based accreditation — that reference is deliberately excluded from ordinary mass-assignment, since only this slice's own flow is meant to set it. A student can legitimately hold more than one record for the same course over time (a genuine attempt, a later accreditation, a retake); nothing here is uniquely constrained to one row per student/course.

**Corrected against this project's own architecture doc:** the doc previously described a separate accreditation entity linking a student's record, the target course, and the granting equivalency, with its own label field. That entity does not exist. Accreditation is not a join/audit record pointing at a student's academic record — it *is* a student's academic record: a new row is written directly for the target course, with its status set to the equivalency-accreditation case and its equivalency reference set to whichever equivalency granted it. The "Accredited by equivalency — Resolution [number]" label is produced at display time by following that reference through to the equivalency's resolution number; it is not a stored value. The doc also referred to "test/simulated students" as though a marker distinguishes them from real ones — no such marker exists, because the entire student-record subsystem in this codebase is, by construction, the informational system this requirement describes; there is no other, "real" student-record system alongside it to distinguish from.

**Direction eligibility is not separately defined here — it is the same graph the equivalency-graph-integrity slice already resolved for cycle detection, read for a different purpose.** An equivalency oriented "old plan counts toward new" produces a directed edge from the source course to the target course: passing the source qualifies the student for the target. One oriented "new plan counts back toward old" produces the edge the other way — passing the target qualifies the student for the source, the reverse of how the pair is stored, the same subtlety already flagged as easy to get backwards in that slice. A bidirectional equivalency produces both edges: passing either qualifies for the other. This slice's whole rule reduces to: given a course a student has passed, and the graph of edges formed by currently-active equivalencies, accredit whatever the edge points to — no independent direction logic belongs here; it must be the same mapping the graph-integrity slice owns, read through its repository/service rather than re-derived.

**Not yet built:** any domain, application, or presentation code for this slice.

## Implementation

1. A "student passed a course" fact and a "currently-active equivalency" both need to raise a signal the rest of the system can react to: an equivalency becoming active raises one after it persists successfully; a student's academic record transitioning to passed raises the other. Both are handled by the same underlying accreditation operation, invoked from two separate listeners rather than duplicated — one asks "who already qualifies for this newly-active equivalency," the other asks "does this newly-passed course now qualify under something already active."
2. Core operation, pure and side-effect-free at its center: given a passed course and the current active-equivalency graph, determine every course it qualifies the student for (following the resolved edge orientation, including both directions for a bidirectional edge, and every matching active equivalency if more than one shares the same source).
3. For each qualifying target: skip if the student already holds a record for that course that already means it's satisfied one way or another (passed directly, already accredited by equivalency, or already accredited by another kind of validation); skip if an accreditation record for this exact student/course/equivalency combination already exists; otherwise write a new academic record for the student on the target course, with the equivalency-accreditation status and a reference to the granting equivalency.
4. This slice depends on the equivalency-graph-integrity slice's active-edge data and edge-orientation logic as a read dependency, and depends on being notified of both trigger events described above — it does not poll for either.

## Config / secrets

Not applicable — no new environment variables or credentials introduced by this slice.

## Acceptance criteria

- [ ] Saving an equivalency oriented "old plan counts toward new" causes every student already holding a passed record for the source course to receive an equivalency-accreditation record for the target course, citing the resolution number.
- [ ] No student receives accreditation in a direction the equivalency does not permit — verified explicitly for the reverse case: an "old plan counts toward new" equivalency must never accredit anyone by reading the graph backwards.
- [ ] A bidirectional equivalency accredits in both directions.
- [ ] A student who passes the qualifying course *after* the equivalency was already active is accredited at that later time, without the equivalency needing to be resaved or reprocessed.
- [ ] Reprocessing the same equivalency/student combination never creates a duplicate accreditation record.
- [ ] A student who already holds a passed, equivalency-accredited, or other-validation-accredited record for the target course does not receive a redundant additional accreditation record.
- [ ] An equivalency transitioning to superseded leaves every accreditation record already granted while it was active untouched, and grants no further ones afterward.

## How to test

Domain-level: unit test the core "passed course qualifies for X" operation directly against plain input — a passed-course fact, a small active-equivalency graph, and a student's existing records — with no database and no framework involved. Cover each direction case, the bidirectional case, the already-satisfied skip, and the duplicate guard.

Feature-level, both triggers exercised separately: (1) give a student a passed record for a course, then save a new equivalency naming that course as the qualifying source, and confirm the accreditation record now exists; (2) save the equivalency first, then transition a student's record for the qualifying course to passed afterward, and confirm the accreditation follows from that later event too. Also test the reverse-direction negative case explicitly, and the superseded-equivalency case: grant an accreditation, supersede the equivalency, and confirm the prior accreditation record is untouched and no new ones appear.

## Risks / edge cases

- **The second trigger means this slice reacts to student-record changes originating anywhere else in the system**, not only to equivalency-save events. Any other code path that ever transitions a student's record to passed becomes a place this slice's behavior implicitly fires — that coupling has to be an explicit signal other code is expected to raise, never something this slice discovers by re-scanning records on a schedule.
- **A single course can be the qualifying source of more than one simultaneously-active equivalency** (different targets, or different directions) — a passed-course event must check every matching active edge, not stop at the first one found.
- **The edge-orientation mapping is a hard, shared dependency on the equivalency-graph-integrity slice** — if that mapping changes, this slice's accreditation direction changes with it automatically. It must be one implementation both slices call, never two independently maintained copies of the same rule.
- **Leaving a superseded equivalency's past accreditations untouched means a student's accredited-record history can reference an equivalency that is no longer active.** Any display of "accredited by equivalency, resolution X" is showing a historical fact, not asserting the referenced equivalency is currently in force — it must not be presented as if it were.
