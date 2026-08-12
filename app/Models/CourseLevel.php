<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CourseLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $level_id
 * @property int $course_id
 * @property int $credits
 * @property Carbon|null $created_at
 */
#[Table(name: 'course_level')]
#[Fillable(['level_id', 'course_id', 'credits'])]
class CourseLevel extends Pivot
{
    /** @use HasFactory<CourseLevelFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
