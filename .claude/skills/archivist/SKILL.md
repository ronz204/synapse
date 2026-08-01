---
name: archivist
description: Use whenever creating or updating anything in this project's Claude Code knowledge base — docs under .claude/docs/ (e.g. overview.md, approach.md), rules under .claude/rules/ (e.g. coding.md), subagents under .claude/agents/, or new skills under .claude/skills/. Trigger this any time the user asks to document architecture, write up a module, record a database/schema decision, capture a coding convention, define a new subagent, or scaffold a new skill — even without saying "documentation", e.g. "we just decided X, let's write that down", "make an agent that reviews migrations", or "turn this into a skill". Always inspect the actual codebase before writing anything; never document from memory or from the conversation alone.
---

# Archivist

Creates and updates every artifact in this project's Claude Code knowledge base: context docs, rules, subagents, and skills. What makes this different from writing any one of those from scratch is twofold — every claim is grounded in the actual codebase instead of assumption, and every artifact lands in the right *kind* of file instead of getting crammed into whichever one is open. The live docs are under `.claude/docs/`.

---

## Step 0 — Read before you write

Never write or edit any of these from memory, from what the user just said, or from what an older doc says. Read the actual source first.

- If the artifact concerns a feature or module, find and read the relevant source: the relevant folder inside `app/` (`Actions`, `Livewire`, `Http`, `Models`, `Providers`), `database/migrations`, `routes/`, `config/`, `composer.json`, existing tests. Once the domain layer exists (this project is built under Hexagonal/DDD — see `.claude/docs/approach.md`), its dedicated framework-free package/namespace is the source of truth for business rules, not the Laravel adapters calling into it.
- If it concerns infrastructure (services, environment, hosting), check the actual provisioning files (`compose.yml`, `.env.example`, `config/*.php`), not just what was decided in conversation — decisions drift from implementation.
- If the user references a decision made earlier in chat ("ya cambiamos a X"), verify it landed in code before documenting it as current. If it hasn't landed yet, say so and ask whether to document it as current or as planned.
- Skim what already exists in `.claude/docs/`, `.claude/rules/`, `.claude/agents/`, and `.claude/skills/` so you don't duplicate or contradict something that's already documented elsewhere.

If code contradicts an existing doc, or contradicts what the user just described, don't silently resolve it — surface the conflict and let the user decide which one is current.

---

## Step 1 — Figure out what kind of artifact this is

| Content is... | Goes in |
|---|---|
| Product vision, scope boundaries, what problem the system solves, core domain concepts at a conceptual level | `.claude/docs/overview.md` |
| Functional requirements, acceptance criteria, mandatory stack/architecture constraints, process/grading constraints | `.claude/docs/approach.md` |
| Per-feature functional spec: flows, data shape, what a feature actually does step by step (once it outgrows `approach.md`) | `.claude/docs/modules.md` |
| Stack choices + rationale, hexagonal layer boundaries (domain vs. Laravel/Livewire adapters), request flow, auth model | `.claude/docs/structure.md` |
| Static infrastructure facts: schema conventions, seeded catalogs, migration patterns — things provisioned once, rarely touched again | `.claude/docs/database.md` |
| A convention that must be **applied every time** a certain kind of file is written or edited (Eloquent model conventions, migration patterns, a style rule) | `.claude/rules/<topic>.md`, scoped via `paths:` frontmatter |
| A repeatable, isolated *task* — something you'd want to hand off with its own tool restrictions and its own context window (review migrations, run a specific test suite, audit domain/framework boundary violations) | `.claude/agents/<name>.md` |
| A capability Claude Code should reach for across many tasks, potentially with bundled scripts/templates/references | `.claude/skills/<name>/SKILL.md` |

Only `overview.md` and `approach.md` currently exist under `.claude/docs/`; `modules.md`, `structure.md`, and `database.md` are candidate files for when their kind of content shows up — don't create them speculatively.

The doc-vs-rule line is about repetition (read once vs. applied every time). The rule-vs-agent line is about isolation: a rule injects context into whatever's already happening; an agent is a separate worker with its own system prompt, its own tool access, and its own context window, invoked for a bounded task. The agent-vs-skill line is about surface: an agent is one specialized worker; a skill is a capability (potentially with scripts/references/assets) that the main session or an agent can pull in.

If content doesn't cleanly fit an existing file, propose either a new section in the closest existing doc or a new file following the same naming/structure pattern already in use — don't force it into an unrelated file just to avoid creating one.

---

## Step 2 — Writing conventions for docs and rules

These apply to `.claude/docs/*.md` and `.claude/rules/*.md`. They exist because earlier drafts got corrected for violating them:

- **English**, regardless of what the source material or conversation was in.
- **Pure technical POV.** Strip product pitch, ROI framing, competitive positioning, sales language, business/legal framing — even if the source had it. If a business fact drives an engineering decision (e.g. a data-protection law shaping a storage choice), state the *engineering implication*, not the business rationale.
- **Self-contained files.** Docs in `.claude/docs/` don't reference each other ("see X.md") — each should stand alone. Rules may reference a doc once if the split genuinely creates a dependency — keep it to the minimum, prefer restating a short fact over adding a pointer.
- **Plain section headers**, no emoji.
- **Tables** for comparisons, stack choices with rationale, risk/complexity summaries.
- **Fenced code blocks** for SQL, JSON, config snippets, ASCII diagrams — never describe a schema or query in prose when a code block says it exactly.
- **Explain why, not just what.** A convention stated without its reasoning gets silently violated the first time someone doesn't see why it matters.
- **Length:** docs stay ~100–200 lines — a section growing past that is a sign it belongs in a more specific file. Rules can run longer since they only load conditionally, but stay scoped to one concern.
- **Non-goals sections earn their place** when a scope boundary is easy to violate by accident.

### Rule mechanics

`.claude/rules/<name>.md` loads automatically when Claude Code reads a file matching the `paths:` glob patterns in the frontmatter — same priority as `CLAUDE.md`.

```yaml
---
paths:
  - "app/Models/**/*.php"
  - "database/migrations/**/*.php"
---
```

- Quote every glob pattern (YAML requires it for patterns starting with `*` or `{`).
- Omit `paths:` entirely only for a rule meant to apply globally — this should be rare.
- One rule file per concern — a new convention with different trigger paths is a new rule, not an addition to an unrelated one.

---

## Step 3 — Writing subagents (`.claude/agents/`)

A subagent is a markdown file with YAML frontmatter; the body is its system prompt. It runs in its own context window with its own tool access — use it for a bounded, repeatable task you'd otherwise delegate the same way every time.

```yaml
---
name: domain-boundary-reviewer
description: Reviews the domain layer for Hexagonal/DDD boundary violations — any import of Laravel, Livewire, or Alpine.js classes inside domain code. Use after generating or editing anything in the domain package, or before merging equivalency/cycle-detection/modality logic.
tools: Read, Glob, Grep
model: sonnet
---

You are reviewing domain code for Synapse. Check specifically for:
- no `use Illuminate\...`, `use Livewire\...`, or Alpine.js references inside the domain package
- domain services (cycle detection, contradiction resolution, modality rules) depend only on ports/interfaces, never on Eloquent models directly
- Laravel/Livewire code only calls *into* the domain through a port — never the reverse
- ...
```

- **`description` is the trigger** — same rule as skills: be specific about when to invoke it, don't undersell it.
- **Restrict `tools` to the minimum** the task needs — a review/analysis agent shouldn't get write access it doesn't use.
- **Choose `model` deliberately**: cheaper/faster models for narrow search or lint-style checks, the default for anything requiring judgment.
- **Ground the system prompt in this project's actual conventions** (the domain/framework boundary, the port-and-adapter split) rather than generic reviewer advice — pull specifics from `.claude/rules/` and `.claude/docs/` rather than restating boilerplate.
- Names must be unique across `.claude/agents/` — a collision gets silently discarded, not merged.

---

## Step 4 — Writing new skills (`.claude/skills/<name>/SKILL.md`)

A skill's `name` + `description` are always in context; the body loads only when it triggers; anything under `scripts/`, `references/`, or `assets/` loads on demand.

- **`description` does double duty**: state what the skill does *and* when to use it, and lean slightly pushy — Claude tends to under-trigger skills, so spell out phrasings and contexts explicitly rather than trusting a vague description to be inferred.
- Keep the `SKILL.md` body under ~500 lines. If it's growing past that, split stable reference material into `references/*.md` and point to it from the body rather than inlining everything.
- Write instructions in the imperative, and explain *why* rather than issuing bare musts — same principle as docs and rules.
- Every new skill goes through the same Step 0 discipline: don't invent its instructions from a template — base them on how the actual task is done in this project, checking existing docs/rules/agents first so the new skill doesn't duplicate or contradict what already exists.

---

## Step 5 — Before presenting the draft

- Re-read the finished artifact once as if you'd never seen the project: does every claim trace back to something actually read in Step 0, not something assumed?
- Check it against the length/format norms for its type (doc, rule, agent, skill).
- If you touched an existing file, confirm you didn't reintroduce a cross-reference between docs, or product/business language that was previously stripped.
- If Step 0 turned up a conflict between artifacts, or between an artifact and the code, lead with that when presenting the draft — don't bury it at the end.