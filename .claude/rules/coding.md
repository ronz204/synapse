# Clean Code & Design Principles

Language-agnostic. Applies on top of the Laravel-specific conventions loaded via the `laravel-best-practices` skill (`architecture.md`, `style.md`, etc.) — this rule is about how code is *shaped*, not Laravel syntax specifics.

This project is built under a **Hexagonal (Ports & Adapters) + DDD** architecture: domain logic (the equivalency graph, cycle detection, contradiction resolution, modality rules) must not import Laravel, Livewire, or Alpine.js classes. Laravel/Livewire acts as an adapter layer calling into that domain, never the reverse. Every principle below should be read with that boundary in mind.

---

## SOLID

- **Single Responsibility.** A module/class/function changes for one reason. In this codebase that means: an Action does one write operation (`app/Actions/Fortify/CreateNewUser.php` only creates a user), a Livewire component owns one screen's state/interaction (`app/Livewire/Settings/Profile.php`), and once the domain layer exists, a domain service owns one invariant (e.g. cycle detection is its own service, not folded into the equivalency-save Action). A function that both parses input and performs I/O is a signal to split it.
- **Open/Closed.** Prefer extension points (a new domain strategy, a new Fortify action, a new Livewire component) over editing a stable core to bolt on a variant. Don't over-apply this pre-emptively — see Non-Goals below.
- **Liskov Substitution.** If something is typed as a contract/interface — e.g. `CreateNewUser` implementing Fortify's `CreatesNewUsers`, or a future domain port like `EquivalencyRepository` — every concrete implementation must be usable anywhere the contract is expected, with no surprising narrowing of behavior.
- **Interface Segregation.** Depend on the slice of a type actually used, not the whole surface. A Form Request or Action that needs one field from the authenticated user shouldn't demand the whole model out of convenience if a narrower parameter is cheap. Shared validation slices are pulled into concerns (`app/Concerns/PasswordValidationRules.php`, `ProfileValidationRules.php`) instead of duplicated or over-widened.
- **Dependency Inversion.** High-level flow depends on an abstraction, not a concrete implementation, wherever there's a real reason to swap it — and in this project there's a *mandatory* one: domain logic must depend on a port (e.g. an `EquivalencyRepository` interface), with an Eloquent-backed class as the adapter implementing it, never the other way around. Don't invert a dependency that will only ever have one implementation and no framework boundary to protect — that's indirection with no payoff.

## High cohesion, low coupling

- **Cohesion**: everything inside a class/file should be there because it serves the same purpose. A Livewire component mixing validation rules, persistence, and notification-sending is low cohesion even if each piece is individually clean.
- **Coupling**: a change in one module shouldn't force edits in unrelated modules. Cross-module communication goes through a narrow, explicit surface (a method signature, a typed DTO/value object) — not shared mutable state, not reaching into another class's internals.
- Concretely: `app/Actions`, `app/Livewire`, `app/Models`, `app/Http` each hold one kind of concern. Once the domain layer lands, it belongs in its own namespace/package that the rest of `app/` calls into — never the reverse import.

## Design patterns — reach for them, don't force them

Patterns are a means to satisfy the above, not a checklist. Common ones worth recognizing when they fit:

| Pattern | Fits when |
|---|---|
| Strategy | Multiple interchangeable ways to do the same thing, selected at runtime — the cycle-detection or contradiction-resolution algorithm is a natural fit if more than one approach needs to coexist |
| Adapter | Wrapping a framework/SDK behind an interface your domain code depends on instead — this *is* the hexagonal boundary: an Eloquent-backed class adapting a domain port |
| Factory | Construction logic itself has branching/complexity worth isolating from the caller |
| Repository | Data access needs to be swappable or mockable independent of business logic — required here so the domain layer never touches Eloquent directly |

If applying a pattern adds a layer of indirection with only one real caller and no foreseeable second one, it's premature — plain code wins.

## Practical rules

- Small functions, one level of abstraction per function body. If a function mixes "what" (business intent) and "how" (low-level detail), extract the "how" into a named helper.
- Guard clauses over nested `if`/`else` pyramids — return/throw early.
- Prefer pure functions (same input → same output, no side effects) wherever the logic doesn't inherently need I/O or mutation. This matters most in the domain layer — cycle detection, contradiction checks, and modality rules should be pure and testable without a database. Push side effects (persistence, mail, notifications) to the edges: Actions, Livewire components, listeners, jobs.
- Composition over inheritance. Inheritance is for genuine is-a relationships with shared behavior; default to composing smaller pieces otherwise.
- No silent failures. An error either gets handled meaningfully at that level or propagates — never caught and discarded, never a swallowed exception turned into `null` with no signal to the caller.
- Naming carries intent: a reader shouldn't need to open a function to guess what it does from its name. Prefer a slightly longer, precise name over a short, ambiguous one.

## Non-goals

- Don't introduce an interface, factory, or abstraction layer for a hypothetical second implementation that doesn't exist yet (YAGNI). Three near-identical lines beat a premature shared abstraction.
- Don't split a cohesive, small function into multiple files/functions just to "apply SRP" — SRP is about reasons to change, not line count.
- These principles don't override a more specific rule. If a `laravel-best-practices` rule file states a concrete convention, that convention wins over a generic pattern preference here.
