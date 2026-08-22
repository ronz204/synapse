<div>
    <x-ui.data-table
        :headers="[
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'implementationYear', 'label' => __('Year'), 'sortable' => true],
                ['key' => 'classification', 'label' => __('Classification'), 'sortable' => true],
                ['key' => 'enrollmentClosingDate', 'label' => __('Closing date'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['name']"
        :paginator="$studyPlans ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="2fr 0.8fr 1fr 1.2fr 1.2fr"
        :can-create="Auth::user()->can('create', \Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Curriculum\StudyPlan\Domain\Entities\StudyPlan::class)"
        :title="__('Study plans')">

        @if ($tableMode === 'client')
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span x-text="row.name"></span>
                <span x-text="row.implementationYear"></span>
                <span>
                    <span class="status-badge muted" x-show="row.isTerminal">{{ __('Terminal') }}</span>
                    <span class="status-badge custom" x-show="!row.isTerminal">{{ __('Active') }}</span>
                </span>
                <span x-text="row.enrollmentClosingDate ?? '—'"></span>
                <div class="actions-cell">
                    <button type="button" class="action-icon view" @click="$wire.viewStructure(row.id)" title="{{ __('View structure') }}" aria-label="{{ __('View structure') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('study_plans.edit')"
                        edit-action="$wire.openEditModal(row.id)" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        @forelse ($studyPlans as $plan)
        <div class="data-row" role="row">
            <span>{{ $plan->name() }}</span>
            <span>{{ $plan->implementationYear() }}</span>
            <span>
                @if ($plan->classification() === \App\Enums\PlanClassification::Terminal)
                <span class="status-badge muted">{{ __('Terminal') }}</span>
                @else
                <span class="status-badge custom">{{ __('Active') }}</span>
                @endif
            </span>
            <span>{{ $plan->enrollmentClosingDate()?->format('Y-m-d') ?? '—' }}</span>
            <div class="actions-cell">
                <button type="button" class="action-icon view" wire:click="viewStructure({{ $plan->id() }})" title="{{ __('View structure') }}" aria-label="{{ __('View structure') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $plan)"
                    edit-action="$wire.openEditModal({{ $plan->id() }})" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.pdf-export-status :id="$pdfExportId" :status="$pdfExportStatus" />

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New study plan') : __('Edit study plan')">
        <div class="form-field">
            <label for="planProgram">{{ __('Program') }}</label>
            <select id="planProgram" wire:model="form.programId" class="{{ $errors->has('form.programId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a program...') }}</option>
                @foreach ($programOptions as $program)
                <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                @endforeach
            </select>
            @error('form.programId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="planName">{{ __('Plan name') }}</label>
            <input type="text" id="planName" wire:model="form.name" placeholder="{{ __('E.g. Plan 2024') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="planYear">{{ __('Implementation year') }}</label>
            <input type="number" id="planYear" wire:model="form.implementationYear" class="{{ $errors->has('form.implementationYear') ? 'has-error' : '' }}">
            @error('form.implementationYear') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="planClassification">{{ __('Classification') }}</label>
            <select id="planClassification" wire:model.live="form.classification" class="{{ $errors->has('form.classification') ? 'has-error' : '' }}">
                @foreach (\App\Enums\PlanClassification::cases() as $classification)
                <option value="{{ $classification->value }}">{{ __($classification->name) }}</option>
                @endforeach
            </select>
            @error('form.classification') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        @if ($form->classification === \App\Enums\PlanClassification::Terminal->value)
        <div class="form-field">
            <label for="planClosingDate">{{ __('Enrollment closing date') }}</label>
            <input type="date" id="planClosingDate" wire:model="form.enrollmentClosingDate" class="{{ $errors->has('form.enrollmentClosingDate') ? 'has-error' : '' }}">
            @error('form.enrollmentClosingDate') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        @endif

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
