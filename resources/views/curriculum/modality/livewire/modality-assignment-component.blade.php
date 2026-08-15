<div>
    <x-ui.data-table
        :headers="[
                ['key' => 'code', 'label' => __('Code'), 'sortable' => true],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'modalityName', 'label' => __('Modality'), 'sortable' => false],
                ['key' => 'resolutionWindow', 'label' => __('Backing resolution'), 'sortable' => false],
            ]"
        mode="client"
        :rows="$rows"
        :searchable="['code', 'name']"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 2fr 1fr 1.5fr 0.8fr"
        :can-create="Auth::user()->can('create', \Src\Curriculum\Modality\Domain\Entities\ModalityResolution::class)"
        create-action="$wire.openAssignModal()"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Curriculum\Modality\Domain\Entities\ModalityResolution::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Curriculum\Modality\Domain\Entities\ModalityResolution::class)"
        :title="__('Course modality assignments')">

        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span class="font-mono text-xs" x-text="row.code"></span>
                <span x-text="row.name"></span>
                <span x-text="row.modalityName ?? '{{ __('Default (Presencial)') }}'"></span>
                <span>
                    <template x-if="row.resolutionNumber">
                        <span>
                            <a href="#" @click.prevent="$wire.downloadDocument(row.resolutionId)" x-text="row.resolutionNumber"></a>
                            <span x-text="'(' + row.validFrom + (row.validTo ? ' → ' + row.validTo : ' → ' + '{{ __('no end date') }}') + ')'"></span>
                            <span class="status-badge system" x-show="row.isCurrentlyValid">{{ __('Valid') }}</span>
                            <span class="status-badge custom" x-show="!row.isCurrentlyValid">{{ __('Expired') }}</span>
                        </span>
                    </template>
                    <template x-if="!row.resolutionNumber && row.requiresResolution">
                        <span class="status-badge custom">{{ __('None on file') }}</span>
                    </template>
                    <template x-if="!row.requiresResolution">
                        <span>&mdash;</span>
                    </template>
                </span>
                <div class="actions-cell"></div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="__('Assign modality to course')">
        <div class="form-field">
            <label for="assignmentCourse">{{ __('Course') }}</label>
            <select id="assignmentCourse" wire:model="form.courseId" class="{{ $errors->has('form.courseId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a course...') }}</option>
                @foreach ($courseOptions as $course)
                <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                @endforeach
            </select>
            @error('form.courseId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="assignmentModality">{{ __('Modality') }}</label>
            <select id="assignmentModality" wire:model.live="form.modalityId" class="{{ $errors->has('form.modalityId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a modality...') }}</option>
                @foreach ($modalityOptions as $modality)
                <option value="{{ $modality['id'] }}" data-requires-resolution="{{ $modality['requiresResolution'] ? '1' : '0' }}">{{ $modality['name'] }}</option>
                @endforeach
            </select>
            @error('form.modalityId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        @php
            $selectedModality = collect($modalityOptions)->firstWhere('id', $form->modalityId);
            $requiresResolution = $selectedModality['requiresResolution'] ?? false;
        @endphp

        @if ($requiresResolution)
        <p class="text-sm">
            {{ __('This modality requires a currently-valid resolution on file. Leave the fields below empty to rely on one already on file, or fill them in to file a new one now.') }}
        </p>

        <div class="form-field">
            <label for="assignmentResolutionNumber">{{ __('Resolution number') }}</label>
            <input type="text" id="assignmentResolutionNumber" wire:model="form.resolutionNumber" class="{{ $errors->has('form.resolutionNumber') ? 'has-error' : '' }}">
            @error('form.resolutionNumber') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="assignmentApprovingBody">{{ __('Approving body') }}</label>
            <input type="text" id="assignmentApprovingBody" wire:model="form.approvingBody" class="{{ $errors->has('form.approvingBody') ? 'has-error' : '' }}">
            @error('form.approvingBody') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="assignmentValidFrom">{{ __('Valid from') }}</label>
            <input type="date" id="assignmentValidFrom" wire:model="form.validFrom" class="{{ $errors->has('form.validFrom') ? 'has-error' : '' }}">
            @error('form.validFrom') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="assignmentValidTo">{{ __('Valid to (optional)') }}</label>
            <input type="date" id="assignmentValidTo" wire:model="form.validTo" class="{{ $errors->has('form.validTo') ? 'has-error' : '' }}">
            @error('form.validTo') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="assignmentDocument">{{ __('Resolution document (PDF)') }}</label>
            <input type="file" id="assignmentDocument" wire:model="form.document" class="{{ $errors->has('form.document') ? 'has-error' : '' }}">
            @error('form.document') <span class="form-error">{{ $message }}</span> @enderror
        </div>
        @endif

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="assign">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>
</div>
