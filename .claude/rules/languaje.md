---
paths:
  - "app/**/*.php"
  - "database/**/*.php"
  - "routes/**/*.php"
  - "tests/**/*.php"
---

# Modern PHP Language Practices

Complements the PHP rules already in `CLAUDE.md` (curly braces, constructor promotion, explicit return types, TitleCase enum keys, PHPDoc over inline comments) — this file covers language-level craftsmanship those don't: strictness, immutability, and closed-vocabulary modeling. Project targets PHP 8.3+ (see `composer.json`), with PHP 8.5 as the expert baseline per Boost guidelines.

## Declare `strict_types` in Every File

Without it, PHP silently coerces scalar arguments (`"3"` → `3`, `1` → `true`) instead of raising a `TypeError`. This project's core invariants — cycle detection, contradiction checks, direction matching — depend on exact type comparisons; a silently coerced value is exactly the kind of bug that survives code review and fails only in production.

Incorrect:
```php
<?php

namespace App\Actions;
```

Correct:
```php
<?php

declare(strict_types=1);

namespace App\Actions;
```

None of the current files in `app/` declare this yet — add it to every new file, and backfill existing ones when you touch them.

## Backed Enums for Closed Vocabularies

A value with a fixed, known set of options (a direction, a classification, a modality name) belongs in a backed enum, not a string column validated ad hoc wherever it's used. This project has several: equivalency direction (`old_to_new` / `new_to_old` / `bidirectional`), plan classification (`Active` / `Terminal`), modality names.

Incorrect:
```php
if ($equivalency->direction === 'old_to_new') {
    // ...
}
```

Correct:
```php
enum EquivalencyDirection: string
{
    case OldToNew = 'old_to_new';
    case NewToOld = 'new_to_old';
    case Bidirectional = 'bidirectional';

    public function appliesForwardFrom(Course $course, Equivalency $equivalency): bool
    {
        return match ($this) {
            self::OldToNew => $course->is($equivalency->sourceCourse),
            self::NewToOld => $course->is($equivalency->targetCourse),
            self::Bidirectional => true,
        };
    }
}
```

Put behavior that branches on the enum's cases *on the enum* (or in a `match` against it) instead of scattering `if ($x === 'string')` checks across controllers, Livewire components, and domain services — a new case then forces every call site to be reconsidered, which is exactly the safety `match`'s exhaustiveness gives you.

## `match` Over `switch`

`match` is strict (`===`), has no fallthrough, and returns a value — a `switch` missing a `break` is a classic silent bug `match` makes structurally impossible.

Incorrect:
```php
switch ($status) {
    case 'active':
        $label = 'Active';
    case 'superseded':
        $label = 'Superseded';
        break;
}
```

Correct:
```php
$label = match ($status) {
    'active' => 'Active',
    'superseded' => 'Superseded',
};
```

An unhandled case throws `UnhandledMatchError` instead of silently falling through — for a closed enum, that's the correct failure mode.

## `readonly` for Value Objects and DTOs

Anything representing an immutable fact — a resolved cycle chain, a DTO crossing the domain/Laravel boundary, a value object like a resolution reference — should be `readonly` so it can't be mutated after construction. Mutable DTOs are a common source of bugs where a value looks unchanged at the call site but was silently modified downstream.

```php
final readonly class CycleDetectionResult
{
    /** @param array<int, Course> $chain */
    public function __construct(
        public bool $hasCycle,
        public array $chain = [],
    ) {}
}
```

## Nullsafe and Null-Coalescing Operators

Prefer `?->` and `??=` over manual `isset`/`empty` chains — they're shorter and fail the same way every time, instead of depending on whether a nested key check was written correctly.

Incorrect:
```php
$name = isset($equivalency) && isset($equivalency->resolution) ? $equivalency->resolution->number : null;
```

Correct:
```php
$name = $equivalency?->resolution?->number;
```

## First-Class Callable Syntax

Prefer `foo(...)` over `fn (...$args) => foo(...$args)` when no argument transformation happens — it's shorter and avoids introducing a closure just to forward arguments.

Incorrect:
```php
$courses->map(fn (Course $course) => $course->code());
```

Correct:
```php
$courses->map(Course::code(...));
```

## `final` by Default

Classes not explicitly designed as extension points should be `final`. Open inheritance is a design decision, not a default — an unintended subclass overriding domain behavior (e.g. cycle detection) is a correctness risk, not a convenience. Leave classes non-final only where genuine polymorphism is the point — implementations of a port/interface at the hexagonal boundary, Eloquent models (the framework requires extending them), and Livewire components.

## Custom Exceptions for Domain Failures

A domain rule violation (cycle detected, contradictory equivalency, invalid modality assignment) should throw a named exception, not a generic `\Exception` or a `null`/`false` return the caller has to remember to check.

```php
final class CycleDetectedException extends \DomainException
{
    /** @param array<int, Course> $chain */
    public function __construct(public readonly array $chain)
    {
        parent::__construct('Adding this equivalency would create a directed cycle.');
    }
}
```

This also gives Laravel's exception handling something concrete to render against (a specific validation message, not a generic 500).

## Static Analysis Is a Real Gate, Not a Suggestion

Larastan is already wired at level 7 (`phpstan.neon`, run via `composer run types:check`, part of `composer run test`). Treat a failing `phpstan analyse` the same as a failing test — fix the type issue, don't reach for `@phpstan-ignore` unless the false positive is confirmed and documented inline with why. Raise the level over time as the domain layer solidifies rather than only holding the current baseline.
