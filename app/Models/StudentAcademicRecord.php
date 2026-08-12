<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AcademicRecordStatus;
use Database\Factories\StudentAcademicRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int|null $academic_period_id
 * @property AcademicRecordStatus $status
 * @property string|null $grade
 * @property int|null $equivalency_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'student_academic_records')]
#[Fillable(['student_id', 'course_id', 'academic_period_id', 'status', 'grade'])]
class StudentAcademicRecord extends Model
{
    /** @use HasFactory<StudentAcademicRecordFactory> */
    use HasFactory;

    /**
     * `equivalency_id` is deliberately left out of the fillable: only the
     * accreditation-by-equivalency flow (RC-02b) sets it.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AcademicRecordStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /**
     * @return BelongsTo<Equivalency, $this>
     */
    public function equivalency(): BelongsTo
    {
        return $this->belongsTo(Equivalency::class);
    }
}
