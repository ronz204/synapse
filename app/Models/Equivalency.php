<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquivalencyDirection;
use App\Enums\EquivalencyStatus;
use Database\Factories\EquivalencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_course_id
 * @property int $target_course_id
 * @property EquivalencyDirection $direction
 * @property string $resolution_number
 * @property EquivalencyStatus $status
 * @property int|null $superseded_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'equivalencies')]
#[Fillable(['source_course_id', 'target_course_id', 'direction', 'resolution_number'])]
class Equivalency extends Model
{
    /** @use HasFactory<EquivalencyFactory> */
    use HasFactory;

    /**
     * `status` and `superseded_by_id` are deliberately left out of the
     * fillable: only the contradiction-resolution flow (RC-02) mutates them,
     * never a mass-assigned create.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => EquivalencyDirection::class,
            'status' => EquivalencyStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function sourceCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'source_course_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function targetCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'target_course_id');
    }

    /**
     * @return BelongsTo<Equivalency, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * @return HasMany<Equivalency, $this>
     */
    public function supersededEquivalencies(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_id');
    }

    /**
     * @param  Builder<Equivalency>  $query
     * @return Builder<Equivalency>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EquivalencyStatus::Active);
    }

    /**
     * @param  Builder<Equivalency>  $query
     * @return Builder<Equivalency>
     */
    public function scopeSuperseded(Builder $query): Builder
    {
        return $query->where('status', EquivalencyStatus::Superseded);
    }
}
