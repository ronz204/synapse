<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanClassification;
use Database\Factories\StudyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $program_id
 * @property string $name
 * @property int $implementation_year
 * @property PlanClassification $classification
 * @property Carbon|null $enrollment_closing_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'study_plans')]
#[Fillable(['program_id', 'name', 'implementation_year', 'classification', 'enrollment_closing_date'])]
class StudyPlan extends Model
{
    /** @use HasFactory<StudyPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => PlanClassification::class,
            'enrollment_closing_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasMany<Level, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    /**
     * @return HasMany<Prerequisite, $this>
     */
    public function prerequisites(): HasMany
    {
        return $this->hasMany(Prerequisite::class);
    }

    /**
     * @return BelongsToMany<Student, $this, StudentPlan>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_plan')
            ->using(StudentPlan::class)
            ->withPivot('current_level');
    }

    /**
     * @param  Builder<StudyPlan>  $query
     * @return Builder<StudyPlan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('classification', PlanClassification::Active);
    }

    /**
     * @param  Builder<StudyPlan>  $query
     * @return Builder<StudyPlan>
     */
    public function scopeTerminal(Builder $query): Builder
    {
        return $query->where('classification', PlanClassification::Terminal);
    }
}
