<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Curriculum\StudyPlan\Presentation\Livewire\StudyPlanComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('study-plans', StudyPlanComponent::class)
    ->name('curriculum.study_plan.index');
