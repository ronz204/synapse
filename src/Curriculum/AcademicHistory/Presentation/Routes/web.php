<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Curriculum\AcademicHistory\Presentation\Livewire\AcademicHistoryComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('academic-history', AcademicHistoryComponent::class)
    ->name('curriculum.academic_history.index');
