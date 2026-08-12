<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $study_plan_id
 * @property int $number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'levels')]
#[Fillable(['study_plan_id', 'number'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class);
    }

    /**
     * @return BelongsToMany<Course, $this, CourseLevel>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_level')
            ->using(CourseLevel::class)
            ->withPivot('credits');
    }
}
