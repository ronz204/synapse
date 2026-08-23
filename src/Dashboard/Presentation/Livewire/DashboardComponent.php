<?php

declare(strict_types=1);

namespace Src\Dashboard\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\Dashboard\Application\UseCases\GetDashboardSummaryUseCase;

#[Layout('layouts.dashboard', ['title' => 'Main Panel', 'subtitle' => 'General overview of the academic system'])]
final class DashboardComponent extends Component
{
    public function render(GetDashboardSummaryUseCase $useCase): View
    {
        return view('dashboard.livewire.dashboard-component', [
            'summary' => $useCase->handle(),
        ]);
    }
}
