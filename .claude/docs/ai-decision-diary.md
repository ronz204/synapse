# AI Decision Diary

Required by the project constitution: what was asked of AI, what was accepted or rejected and why,
and — explicitly — real cases where AI output was wrong and had to be corrected. Written as the work
happened, not reconstructed afterwards.

---

## 2026-08-18 — Feature 002-perceived-performance

### What was asked

Make the system feel instant: pressing a module or a button should load fast enough that the wait is
not perceptible. Run through the full Spec Kit flow (specify → plan → tasks → implement).

### Accepted

**Measure before optimising.** The plan put the volume seeder and the measurement harness in the
foundational phase, ahead of every optimisation, and captured `baseline.json` before touching a line
of production code. This turned out to be the single most valuable decision: without it, every
number claimed afterwards would have been an assertion rather than evidence.

**Server-side pagination only past a threshold.** The reflexive move is to paginate everything. It
was rejected in favour of a 200-row threshold, because client mode genuinely wins below that — every
sort and filter is free. Migrating Roles, Modalities, Permissions and Study Plans would have made
four modules measurably slower to fix three.

**Refusing to cache the equivalency graph.** Written into the plan as a hard constraint before
anyone could propose it in good faith. A cached `activeGraph()` would let a cycle through, silently,
and Principle II of the constitution puts that above any performance gain.

### Rejected

**Queueing the Excel export alongside the PDF one.** The original plan (research decision D-04) said
queue both. On implementation this was narrowed: the cost being avoided is Browsershot booting
headless Chromium, which only the PDF path pays. Queueing Excel would have added moving parts to
something already fast.

**Aligning the client-mode search debounce to the server one.** Task T038 called for making both
250 ms. Rejected: client mode filters an array already in the browser and issues no query, so there
is nothing to coalesce and raising its delay would only make the fast path feel slower. FR-008 is
about queries; client mode makes none. Server mode went 400 ms → 250 ms, client stayed at 150 ms.

### Where the AI output was wrong

**1. Hallazgo H7 — "livewire/blaze is installed but no component uses it."**

Recorded in `plan.md` as a diagnostic finding, on the evidence that no `#[Blaze]` attribute appeared
anywhere in `app/` or `src/`. Task T060 was written to "adopt it where it rinde".

That reading was wrong. Blaze is not opt-in per component: `vendor/livewire/blaze/config/blaze.php`
defaults `enabled` to `true`, and the package hooks the Blade compiler globally. It had been active
the whole time, including during the baseline.

Corrected by measuring in the opposite direction — running the harness with `BLAZE_ENABLED=false`
and comparing against `true`. First attempt showed a large apparent win for Blaze (courses 61 ms vs
127 ms), but that comparison was invalid: the "off" run followed a `view:clear` and paid Blade
compilation on first render while the "on" run did not. Re-run with `view:clear` before both:

| Module | Blaze on | Blaze off |
|---|---|---|
| courses | 124 ms | 127 ms |
| equivalencies | 87 ms | 94 ms |
| roles | 146 ms | 127 ms |
| modality-assignments | 155 ms | 199 ms |

No reproducible difference on this workload — roles was even slower with it on. Outcome: Blaze stays
enabled because it is the default and costs nothing, but **no gain is claimed for it**, T061 does not
apply, and the H7 finding in `plan.md` is wrong as written.

Two lessons, both about the same failure: absence of an annotation is not absence of behaviour, and
a comparison with an uncontrolled variable in it will happily produce a confident wrong number.

**2. The polling loop that could never end.**

`InteractsWithExports::pollExport()` was written to return early when the cached export status was
missing, treating "no status" as "still working". That is exactly the indefinite spinner FR-009
exists to prevent: an expired TTL or a lost job would leave the indicator turning forever.

Caught by a test written from the contract rather than from the code
(`QueuedExportTest::it clears the indicator and says so when the export fails`), which failed on the
first run. Fixed so a missing status ends the wait with an explicit message. Worth noting that the
code looked reasonable and the bug was only visible from the requirement's side.

**3. `renderServerMode()` had never actually worked in Equivalencies.**

Not an AI error introduced by this feature, but found by it. The method built a `LengthAwarePaginator`
over raw domain entities while the view read array keys — the server path had never been rendered, so
the mismatch had gone unnoticed. Switching the module to server mode would have produced a runtime
error in production; it was fixed in the same change.

### Measured outcome

Equivalencies module open, at the target volume (800 courses, 500 equivalencies, 2.000 students):

| | Baseline | After |
|---|---|---|
| SQL queries per open | 657 | ≤ 10 |
| p95 render | 4.150 ms | 66 ms |

All nine module opens now meet budget B-02 (500 ms p95); the baseline met it for eight of nine.
