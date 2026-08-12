<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StudentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $student_id
 * @property int $study_plan_id
 * @property int|null $current_level
 * @property Carbon|null $created_at
 */
#[Table(name: 'student_plan')]
#[Fillable(['student_id', 'study_plan_id', 'current_level'])]
class StudentPlan extends Pivot
{
    /** @use HasFactory<StudentPlanFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }
}
