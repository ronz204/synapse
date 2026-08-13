<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Curriculum\Equivalency\Presentation\Livewire\EquivalencyComponent;

Route::middleware(['web', 'auth', 'verified'])
    ->get('equivalencies', EquivalencyComponent::class)
    ->name('curriculum.equivalency.index');
