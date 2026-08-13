<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Curriculum\Course\Presentation\Livewire\CourseComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('courses', CourseComponent::class)
    ->name('curriculum.course.index');
