---
paths:
  - "app/Models/**/*.php"
  - "database/migrations/**/*.php"
  - "database/factories/**/*.php"
  - "database/seeders/**/*.php"
---

# Eloquent Practices for This Project

Complements `rules/eloquent.md` and `rules/db-performance.md` in the `laravel-best-practices` skill (relationship typing, scopes, casts, eager loading, indexing — already covered there, not repeated here). This file covers what's specific to this codebase: its attribute-based model style and the Eloquent/domain boundary required by the Hexagonal/DDD architecture (see `.claude/docs/approach.md`).

## Configure Models via Attributes, Not Legacy Properties

Laravel 13 supports PHP attributes for model configuration. `app/Models/User.php` already uses this — follow the same style for every new model instead of reverting to the older `protected $fillable` / `protected $hidden` array properties.

```php
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['number', 'document_path'])]
#[Hidden([])]
class Resolution extends Model
{
    // ...
}
```

## Never Leave a Model Mass-Assignment-Open

`#[Fillable]` (or `protected $fillable`) must always be an explicit allow-list. Never use `protected $guarded = []` — it disables mass-assignment protection entirely, so any unexpected request field silently lands on the model.

Incorrect:
```php
class Equivalency extends Model
{
    protected $guarded = [];
}
```

Correct:
```php
#[Fillable(['source_course_id', 'target_course_id', 'direction', 'resolution_id'])]
class Equivalency extends Model
{
    // status is deliberately excluded — it only changes via the contradiction-resolution flow, never mass assignment
}
```

## Cast Closed-Vocabulary Columns to Backed Enums

Pairs with the enum rule in `rules/languaje.md`. A `direction`, `classification`, or modality-name column should hydrate as its enum, not a raw string — this makes an invalid value a cast-time `ValueError` instead of a value that silently drifts wrong through the rest of the request.

```php
protected function casts(): array
{
    return [
        'direction' => EquivalencyDirection::class,
        'status' => EquivalencyStatus::class,
    ];
}
```

## Keep Models as Persistence Adapters, Not Domain Logic

This project's Hexagonal/DDD constraint means the domain layer (cycle detection, contradiction resolution, direction-matching for accreditation) must not import Eloquent, and by the same boundary, Eloquent models should not accumulate that logic either — a `hasCycleWith()` method on the `Equivalency` model would put a graph algorithm behind an Eloquent call, coupling it to the database and making it untestable without one.

Incorrect:
```php
class Equivalency extends Model
{
    public function wouldCreateCycleWith(Course $source, Course $target): bool
    {
        // graph traversal against $this->newQuery() ...
    }
}
```

Correct: the model stays a plain persistence shape; a domain service (framework-free) receives the current graph — loaded through a repository port an Eloquent-backed adapter implements — and answers the question independently of Eloquent.

Scopes, casts, and relationships belong on the model. Business rules that decide whether a write is *valid* belong in the domain layer, called before the model's `save()`/`create()` runs.

## Wrap Multi-Step Writes in Transactions

Several flows in this system are one unit of work spanning more than one table: saving an `Equivalency` and, on success, writing the resulting `AccreditationRecord`s (RC-02b); saving a `CourseModality` alongside its `Resolution`. A failure partway must not leave the equivalency graph or modality catalog half-written.

```php
DB::transaction(function () use ($equivalencyData, $matchingStudents) {
    $equivalency = Equivalency::create($equivalencyData);

    foreach ($matchingStudents as $record) {
        AccreditationRecord::create([
            'student_academic_record_id' => $record->id,
            'equivalency_id' => $equivalency->id,
        ]);
    }
});
```

## Model Historical State as a Status, Not a Deletion

Superseded equivalencies and expired modality resolutions must stay queryable — this is an explicit requirement, not a soft-delete convenience. Use a `status` column with query scopes rather than Laravel's `SoftDeletes` trait, since "superseded" is a meaningful state transition the UI displays, not a hidden/deleted row.

```php
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', EquivalencyStatus::Active);
}
```

Never call `->delete()` on an `Equivalency` or a `CourseModality`'s resolution history — transition `status` instead.

## Enforce Uniqueness at the Database Level Too

An application-level check for "does a contradictory equivalency already exist" (RC-02) or "is this the active resolution for this course/modality" (RC-03) is necessary but not sufficient — two concurrent requests can both pass the check before either writes. Back every such rule with a real unique or composite-unique index in the migration, and let the resulting `QueryException` surface as a domain-meaningful error rather than a generic 500.

```php
Schema::table('equivalencies', function (Blueprint $table) {
    $table->unique(['source_course_id', 'target_course_id', 'direction', 'status']);
});
```

## Factories Should Build the Edge Cases, Not Just the Happy Path

The acceptance criteria this project is graded on are edge cases (a Terminal plan missing its closing date, a cycle-closing equivalency, a contradictory resolution, a course needing a modality resolution it doesn't have). A factory with only a default `definition()` can't express these — add named states so tests can construct the exact scenario the requirement describes.

```php
class StudyPlanFactory extends Factory
{
    public function terminal(): static
    {
        return $this->state(fn () => [
            'classification' => PlanClassification::Terminal,
            'enrollment_closing_date' => now()->addMonths(6),
        ]);
    }
}
```
