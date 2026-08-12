<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LaboratoryType;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $program_id
 * @property int|null $modality_id
 * @property string $code
 * @property string $name
 * @property bool $is_service
 * @property bool $is_bottleneck
 * @property bool $requires_laboratory
 * @property LaboratoryType|null $laboratory_type
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'program_id',
    'modality_id',
    'code',
    'name',
    'is_service',
    'is_bottleneck',
    'requires_laboratory',
    'laboratory_type',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'laboratory_type' => LaboratoryType::class,
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
     * @return BelongsTo<Modality, $this>
     */
    public function modality(): BelongsTo
    {
        return $this->belongsTo(Modality::class);
    }

    /**
     * @return BelongsToMany<Level, $this, CourseLevel>
     */
    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'course_level')
            ->using(CourseLevel::class)
            ->withPivot('credits');
    }

    /**
     * @return HasMany<ModalityResolution, $this>
     */
    public function modalityResolutions(): HasMany
    {
        return $this->hasMany(ModalityResolution::class);
    }

    /**
     * @return HasMany<Prerequisite, $this>
     */
    public function prerequisitesAsRequiredCourse(): HasMany
    {
        return $this->hasMany(Prerequisite::class, 'required_course_id');
    }

    /**
     * @return HasMany<Prerequisite, $this>
     */
    public function prerequisitesAsDependentCourse(): HasMany
    {
        return $this->hasMany(Prerequisite::class, 'dependent_course_id');
    }

    /**
     * @return HasMany<Equivalency, $this>
     */
    public function equivalenciesAsSource(): HasMany
    {
        return $this->hasMany(Equivalency::class, 'source_course_id');
    }

    /**
     * @return HasMany<Equivalency, $this>
     */
    public function equivalenciesAsTarget(): HasMany
    {
        return $this->hasMany(Equivalency::class, 'target_course_id');
    }

    /**
     * @return HasMany<StudentAcademicRecord, $this>
     */
    public function studentAcademicRecords(): HasMany
    {
        return $this->hasMany(StudentAcademicRecord::class);
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
