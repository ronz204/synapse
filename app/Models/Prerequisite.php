<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PrerequisiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $study_plan_id
 * @property int $required_course_id
 * @property int $dependent_course_id
 * @property Carbon|null $created_at
 */
#[Fillable(['study_plan_id', 'required_course_id', 'dependent_course_id'])]
class Prerequisite extends Model
{
    /** @use HasFactory<PrerequisiteFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function requiredCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'required_course_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function dependentCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'dependent_course_id');
    }
}
