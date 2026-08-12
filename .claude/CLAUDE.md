# Synapse

Synapse is a Laravel application implementing a curricular equivalency repository: it stores study plans and courses, and maintains a directed graph of course equivalencies between old and new study plans so an institution can answer, with an auditable trail, whether a course a student already passed counts under a revised curriculum. The two invariants that matter above everything else are that the equivalency graph never contains a cycle and two resolutions are never left silently contradictory. See `.claude/docs/overview.md` for the full domain framing and `.claude/docs/approach.md` for the graded functional requirements and the mandatory Hexagonal (Ports & Adapters) + DDD architecture constraint.

Laravel Boost's auto-generated agent guidelines (package versions, Artisan/Tinker/Pint/Pest conventions) live in the root `CLAUDE.md`, not here — Boost regenerates that block in place on `php artisan boost:update` and expects it at the project root.

## Knowledge base layout

This project's documentation lives under `.claude/`, alongside the rules/skills that act on it. Know which piece to reach for before you go looking:

- **`.claude/docs/`** (English) — engineering reference cross-checked against the actual codebase: what Synapse is, the graded functional requirements, per-module domain detail. No product/business framing, self-contained files. This is what grounds implementation work.
- **`.claude/rules/`** (English) — conventions that load automatically when a matching file is opened or edited, instead of being read on demand.
- **`.claude/skills/`** — capabilities to reach for across tasks: the project-authored `archivist` and `specifier`, plus the Laravel Boost-installed skills declared in `boost.json` (`fortify-development`, `laravel-best-practices`, `fluxui-development`, `livewire-development`, `pest-testing`, `tailwindcss-development`).
- **`.claude/deltas/`** — finer-grained than `.claude/docs/`: each slice's living `<slice>.spec.md`, plus an optional `<slice>.docs.md` for supplementary theory/context. Flat files, no per-slice subfolder, no decision history.

No `.claude/agents/` in this project — subagents aren't used here; documentation work routes through `archivist` and `specifier`.

**Precedence when a doc disagrees with the code:** the actual code always wins. Don't silently propagate a stale doc — flag the mismatch, and update the file only in the direction of improvement (never regress a doc just to match old content).

### `.claude/docs/` — the engineering reference

Maintained via the `archivist` skill: every claim is checked against the actual codebase, never carried over by assumption.

| File | Purpose | Consult when |
|---|---|---|
| `.claude/docs/overview.md` | What the curricular equivalency domain is and why it exists, the no-cycles/no-silent-contradictions invariants, who uses the system | Confirming whether something is in scope, or why a domain decision was made a certain way |
| `.claude/docs/approach.md` | The graded functional requirements (RC-01 Study Plans, RC-02 Equivalency graph integrity, RC-02b Accreditation, RC-03 Modality Catalog), the mandatory Hexagonal/DDD constraint, and the project's process constraints | Scoping a requirement, or checking an architecture constraint before writing domain code |
| `.claude/docs/modules.md` | Per-module detail for each requirement above: domain entities, data shape, step-by-step flow, and invariants | Implementing or reviewing a specific module's domain logic |

### `.claude/rules/` — conventions applied automatically

| File | Scope (`paths:`) | Covers |
|---|---|---|
| `coding.md` | global (no `paths:`) | SOLID, cohesion/coupling, and design-pattern guidance grounded in this codebase's Hexagonal/DDD boundary |
| `languaje.md` | `app/`, `database/`, `routes/`, `tests/` | Modern PHP language practices beyond what `coding.md` already covers |
| `elocuent.md` | `app/Models/`, migrations, factories, seeders | Eloquent conventions specific to this project: attribute-based model config, models as persistence adapters rather than domain logic, transactional writes, status-based history instead of hard deletes |
| `livewire.md` | `app/Livewire/`, Livewire views/components | This project's established Livewire component format, the Fortify/Livewire boundary in the auth views, action-delegation patterns |

### `.claude/skills/` — capabilities

- **`archivist`** — creates and updates everything above plus each slice's `spec.md`/`docs.md`: `.claude/docs/`, `.claude/rules/`, new skills, and `.claude/deltas/`. Invoke it whenever documenting an architecture decision, writing up a module, capturing a coding convention, or writing/amending a slice's spec — it always inspects the real codebase before writing, never documents from memory or from conversation alone. Writes every artifact from a matching skeleton under its own `references/*.template.md`.
- **`specifier`** — the information-gathering step before a robust spec: investigates a slice's real implementation, checks it against existing docs/rules, and asks the targeted questions code alone can't answer (intent, boundaries, invariants, open decisions), then hands the result to `archivist` to write. Invoke it before creating or hardening a slice's `spec.md` — it never writes into `deltas/` itself.
- The remaining skills under `.claude/skills/` (`fortify-development`, `laravel-best-practices`, `fluxui-development`, `livewire-development`, `pest-testing`, `tailwindcss-development`) are installed and kept current by Laravel Boost per `boost.json`, not authored via `archivist`.

### `.claude/deltas/` — per-slice specs

Spec-Driven Development at the granularity of one cohesive capability (a "slice"), not one source file — e.g. the equivalency graph's cycle-detection logic, or the two-factor authentication flow. One required file per slice, one optional:

- **`<slice>.spec.md`** — the living, current contract: intent, scope/non-goals, contract, invariants, deferred/open questions, acceptance criteria. Edited in place as the slice's reality changes — this is the only durable record of the slice, so it has to stay complete and current on its own.
- **`<slice>.docs.md`** (optional) — supplementary theory or context that doesn't fit the spec's contract shape. Not a decision log — this project deliberately doesn't keep a history of how a spec arrived at its current state, only the current state itself.

`.claude/deltas/` exists but is empty — no slice has been specced yet. Use `specifier` to gather what a new slice's spec needs, then `archivist` to write it.
