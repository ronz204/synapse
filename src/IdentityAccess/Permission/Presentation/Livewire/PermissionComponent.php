<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\IdentityAccess\Permission\Application\UseCases\CreatePermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\DeletePermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\FindPermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\ListPermissionsUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\UpdatePermissionUseCase;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Presentation\Livewire\Forms\PermissionForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.dashboard', ['title' => 'Permissions', 'subtitle' => 'Permissions management assigned to each role'])]
class PermissionComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * Permissions are `modules x actions` — a small, reference-style
     * catalog. Same reasoning as RoleComponent: client-side by default.
     */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    public ?int $editingId = null;

    public PermissionForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Permission::class);
        $this->sortKey = 'module';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Permission::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindPermissionUseCase $useCase): void
    {
        $this->authorize('update', Permission::class);

        $permission = $useCase->handle($id);

        $this->editingId = $id;
        $this->form->fromEntity($permission);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreatePermissionUseCase $createUseCase, UpdatePermissionUseCase $updateUseCase, ListPermissionsUseCase $listUseCase): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Permission::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            $this->authorize('update', Permission::class);
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: $this->editingId === null
            ? __('Permission created.')
            : __('Permission updated.'));
    }

    public function delete(int $id, DeletePermissionUseCase $useCase, ListPermissionsUseCase $listUseCase): void
    {
        $this->authorize('delete', Permission::class);

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: __('Permission deleted.'));
    }

    /**
     * See RoleComponent::exportPdf() for why $search is a parameter here
     * rather than read off $this->search.
     */
    public function exportPdf(PdfExporterInterface $exporter, ListPermissionsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Permission::class);

        return $this->streamPdf(
            __('Permissions'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Permissions')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListPermissionsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Permission::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Permissions')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListPermissionsUseCase $useCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        return $view;
    }

    private function renderClientMode(ListPermissionsUseCase $useCase): View
    {
        return view('identityaccess.permission.livewire.permission-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListPermissionsUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->search !== '' ? $this->search : null,
            module: null,
            perPage: $this->perPage,
            page: $this->page,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        );

        $paginator = new LengthAwarePaginator(
            items: $result['items'],
            total: $result['total'],
            perPage: $this->perPage,
            currentPage: $this->page,
        );

        return view('identityaccess.permission.livewire.permission-component', [
            'tableMode' => 'server',
            'permissions' => $paginator,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Permission $permission): array
    {
        return [
            'id' => $permission->id(),
            'module' => $permission->module(),
            'action' => $permission->action(),
            'name' => $permission->name(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListPermissionsUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * Same projection as freshRows(), but filtered by the search term the
     * user currently has applied so an export mirrors the visible table
     * instead of always dumping the whole catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListPermissionsUseCase $useCase, ?string $search): array
    {
        $candidate = filled($search) ? $search : $this->search;

        return array_map(
            $this->toRow(...),
            $useCase->all(
                search: $candidate !== '' ? $candidate : null,
                sortBy: $this->sortKey,
                sortDir: $this->sortDir,
            ),
        );
    }

    /**
     * Export columns, deliberately mirroring the on-screen <x-ui.data-table>
     * headers so both stay in step.
     *
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'module', 'label' => __('Module')],
            ['key' => 'action', 'label' => __('Action')],
            ['key' => 'name', 'label' => __('Name')],
        ];
    }
}
