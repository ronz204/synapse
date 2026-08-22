<?php

declare(strict_types=1);

namespace Src\Curriculum\AcademicHistory\Presentation\Livewire\Forms;

use Livewire\Form;

final class PassedCourseForm extends Form
{
    public ?int $courseId = null;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['courseId' => ['required', 'integer', 'exists:courses,id']];
    }
}
