<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModalityResolutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $modality_id
 * @property string $resolution_number
 * @property string $approving_body
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'modality_resolutions')]
#[Fillable(['course_id', 'modality_id', 'resolution_number', 'approving_body', 'valid_from', 'valid_to'])]
class ModalityResolution extends Model
{
    /** @use HasFactory<ModalityResolutionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Modality, $this>
     */
    public function modality(): BelongsTo
    {
        return $this->belongsTo(Modality::class);
    }

    /**
     * Currently valid resolutions: the start date has already passed and
     * either there's no end date, or the end date hasn't been reached yet.
     *
     * @param  Builder<ModalityResolution>  $query
     * @return Builder<ModalityResolution>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('valid_from', '<=', now())
            ->where(function (Builder $query) {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });
    }
}
