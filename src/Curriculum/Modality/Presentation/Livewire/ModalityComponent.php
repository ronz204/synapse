<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Src\Curriculum\Modality\Application\UseCases\CreateModalityUseCase;
use Src\Curriculum\Modality\Application\UseCases\DeleteModalityUseCase;
use Src\Curriculum\Modality\Application\UseCases\FindModalityUseCase;
use Src\Curriculum\Modality\Application\UseCases\ListModalitiesUseCase;
use Src\Curriculum\Modality\Application\UseCases\UpdateModalityUseCase;
use Src\Curriculum\Modality\Domain\Entities\Modality;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityInUseException;
use Src\Curriculum\Modality\Domain\Exceptions\ModalityNameAlreadyExistsException;
use Src\Curriculum\Modality\Presentation\Livewire\Forms\ModalityForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.dashboard', ['title' => 'Modalities', 'subtitle' => 'Teaching modality catalog'])]
class ModalityComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * Small reference-style catalog (5+ values by seed, rarely more than a
     * few dozen) — client-side by default, same reasoning as Role/Course.
     */
    protected string $tableMode = 'server';

    public bool $showModal = false;

    /**
     * Null while creating; the row's id while editing. Also read by
     * ModalityForm::rules() to scope the uniqueness check correctly.
     */
    public ?int $editingId = null;

    public ModalityForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Modality::class);
        $this->sortKey = 'name';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Modality::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindModalityUseCase $useCase): void
    {
        $modality = $useCase->handle($id);

        $this->authorize('update', $modality);

        $this->editingId = $id;
        $this->form->fromEntity($modality);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateModalityUseCase $createUseCase, UpdateModalityUseCase $updateUseCase, ListModalitiesUseCase $listUseCase, FindModalityUseCase $findUseCase): void
    {
        $this->form->validate();

        try {
            if ($this->editingId === null) {
                $this->authorize('create', Modality::class);
                $createUseCase->handle($this->form->toDto());
            } else {
                $this->authorize('update', $findUseCase->handle($this->editingId));
                $updateUseCase->handle($this->editingId, $this->form->toDto());
            }
        } catch (ModalityNameAlreadyExistsException $e) {
            $this->addError('form.name', __('A modality named :name already exists.', ['name' => $e->modalityName()]));

            return;
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: $this->editingId === null
            ? __('Modality created.')
            : __('Modality updated.'));
    }

    public function delete(int $id, DeleteModalityUseCase $useCase, ListModalitiesUseCase $listUseCase, FindModalityUseCase $findUseCase): void
    {
        $this->authorize('delete', $findUseCase->handle($id));

        try {
            $useCase->handle($id);
        } catch (ModalityInUseException $e) {
            Flux::toast(variant: 'danger', text: __('Modality with id :id is still in use and cannot be deleted.', ['id' => $e->modalityId()]));

            return;
        }

        $this->refreshTable($this->freshRows($listUseCase));
        Flux::toast(variant: 'success', text: __('Modality deleted.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListModalitiesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Modality::class);

        return $this->streamPdf(
            __('Modalities'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Modalities')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListModalitiesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Modality::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Modalities')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListModalitiesUseCase $useCase): View
    {
        return $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);
    }

    private function renderClientMode(ListModalitiesUseCase $useCase): View
    {
        return view('curriculum.modality.livewire.modality-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListModalitiesUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->search !== '' ? $this->search : null,
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

        return view('curriculum.modality.livewire.modality-component', [
            'tableMode' => 'server',
            'modalities' => $paginator,
        ]);
    }

    /**
     * Plain-array projection handed to Alpine as JSON — keeps the Domain
     * Entity from ever leaking past the Presentation boundary.
     *
     * @return array<string, mixed>
     */
    private function toRow(Modality $modality): array
    {
        return [
            'id' => $modality->id(),
            'name' => $modality->name(),
            'requiresResolution' => $modality->requiresResolution(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListModalitiesUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListModalitiesUseCase $useCase, ?string $search): array
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
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'requiresResolution', 'label' => __('Requires resolution'), 'format' => fn (bool $v): string => $v ? __('Yes') : __('No')],
        ];
    }
}
