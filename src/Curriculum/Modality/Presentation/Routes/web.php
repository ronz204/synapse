<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityAssignmentComponent;
use Src\Curriculum\Modality\Presentation\Livewire\ModalityComponent;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::get('modalities', ModalityComponent::class)->name('curriculum.modality.index');
    Route::get('modality-assignments', ModalityAssignmentComponent::class)->name('curriculum.modality_assignment.index');
});
