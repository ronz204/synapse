<?php

use Illuminate\Support\Facades\Route;
use Src\Dashboard\Presentation\Livewire\DashboardComponent;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardComponent::class)->name('dashboard');
});

require __DIR__.'/settings.php';
