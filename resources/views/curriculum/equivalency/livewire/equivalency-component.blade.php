<div>
    <x-ui.data-table
        :pending-export-id="$pendingExportId"
        :ready-export-id="$readyExportId"
        :headers="[
                ['key' => 'sourceCourseCode', 'label' => __('Source'), 'sortable' => false],
                ['key' => 'targetCourseCode', 'label' => __('Target'), 'sortable' => false],
                ['key' => 'direction', 'label' => __('Direction'), 'sortable' => false],
                ['key' => 'resolutionNumber', 'label' => __('Resolution'), 'sortable' => true],
                ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['sourceCourseCode', 'targetCourseCode', 'resolutionNumber']"
        :paginator="$equivalencies ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1fr 1fr 1fr 1fr 1fr 0.6fr"
        :can-create="Auth::user()->can('create', \Src\Curriculum\Equivalency\Domain\Entities\Equivalency::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Curriculum\Equivalency\Domain\Entities\Equivalency::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Curriculum\Equivalency\Domain\Entities\Equivalency::class)"
        :title="__('Equivalencies')"
        create-action="$wire.openCreateModal()">

        @if ($tableMode === 'client')
        {{-- Client mode: Alpine renders rows from the in-browser `pageRows`
                     collection. See resources/js/data-table.js. --}}
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span class="font-mono text-xs" x-text="row.sourceCourseCode"></span>
                <span class="font-mono text-xs" x-text="row.targetCourseCode"></span>
                <span x-text="row.direction"></span>
                <span x-text="row.resolutionNumber"></span>
                <span>
                    <span class="status-badge system" x-show="row.isActive">{{ __('Active') }}</span>
                    <template x-if="!row.isActive">
                        <div>
                            <span class="status-badge custom">{{ __('Superseded') }}</span>
                            <span class="superseded-hint" x-show="row.supersededByResolutionNumber" x-text="'{{ __('by') }} ' + row.supersededByResolutionNumber"></span>
                        </div>
                    </template>
                </span>
                <div class="actions-cell">
                    <button type="button" class="action-icon download" wire:click="downloadDocument(row.id)" title="{{ __('Download resolution') }}" aria-label="{{ __('Download resolution') }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        {{-- Server mode: unchanged Livewire-driven pagination. --}}
        @forelse ($equivalencies as $equivalency)
        <div class="data-row" role="row">
            <span class="font-mono text-xs">{{ $equivalency['sourceCourseCode'] }}</span>
            <span class="font-mono text-xs">{{ $equivalency['targetCourseCode'] }}</span>
            <span>{{ $equivalency['direction'] }}</span>
            <span>{{ $equivalency['resolutionNumber'] }}</span>
            <span>
                @if ($equivalency['isActive'])
                <span class="status-badge system">{{ __('Active') }}</span>
                @else
                <span class="status-badge custom">{{ __('Superseded') }}</span>
                @if ($equivalency['supersededByResolutionNumber'])
                <span class="superseded-hint">{{ __('by :number', ['number' => $equivalency['supersededByResolutionNumber']]) }}</span>
                @endif
                @endif
            </span>
            <div class="actions-cell">
                <button type="button" class="action-icon download" wire:click="downloadDocument({{ $equivalency['id'] }})" title="{{ __('Download resolution') }}" aria-label="{{ __('Download resolution') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                </button>
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$conflictingEquivalencyId === null ? __('Register equivalency') : __('Resolve contradiction')">
        @if ($conflictingEquivalencyId === null)
        {{-- Plain registration form. --}}
        {{-- Narrows both pickers below. Without it the modal renders the whole
             active catalog — ~800 options each — on every component render. --}}
        <div class="form-field">
            <label for="equivalencyCourseSearch">{{ __('Search courses') }}</label>
            <input id="equivalencyCourseSearch" type="search" wire:model.live.debounce.250ms="courseSearch"
                placeholder="{{ __('Type a code or name to narrow the lists...') }}">
        </div>

        <div class="form-field">
            <label for="equivalencySourceCourse">{{ __('Source course (old plan)') }}</label>
            <select id="equivalencySourceCourse" wire:model="form.sourceCourseId" class="{{ $errors->has('form.sourceCourseId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a course...') }}</option>
                @foreach ($courseOptions as $course)
                <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                @endforeach
            </select>
            @error('form.sourceCourseId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="equivalencyTargetCourse">{{ __('Target course (new plan)') }}</label>
            <select id="equivalencyTargetCourse" wire:model="form.targetCourseId" class="{{ $errors->has('form.targetCourseId') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a course...') }}</option>
                @foreach ($courseOptions as $course)
                <option value="{{ $course['id'] }}">{{ $course['code'] }} — {{ $course['name'] }}</option>
                @endforeach
            </select>
            @error('form.targetCourseId') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="equivalencyDirection">{{ __('Direction') }}</label>
            <select id="equivalencyDirection" wire:model="form.direction" class="{{ $errors->has('form.direction') ? 'has-error' : '' }}">
                <option value="">{{ __('Select a direction...') }}</option>
                @foreach (\App\Enums\EquivalencyDirection::cases() as $direction)
                <option value="{{ $direction->value }}">{{ Str::headline($direction->name) }}</option>
                @endforeach
            </select>
            @error('form.direction') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="equivalencyResolutionNumber">{{ __('Resolution number') }}</label>
            <input type="text" id="equivalencyResolutionNumber" wire:model="form.resolutionNumber" placeholder="{{ __('E.g. R-2026-045') }}" class="{{ $errors->has('form.resolutionNumber') ? 'has-error' : '' }}">
            @error('form.resolutionNumber') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="equivalencyDocument">{{ __('Resolution document (PDF)') }}</label>
            <input type="file" id="equivalencyDocument" wire:model="form.document" accept="application/pdf" class="{{ $errors->has('form.document') ? 'has-error' : '' }}">
            <div wire:loading wire:target="form.document">{{ __('Uploading...') }}</div>
            @error('form.document') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="register" wire:loading.attr="disabled" wire:target="register">{{ __('Confirm') }}</button>
        </x-slot:footer>
        @else
        {{-- Contradiction detected: show both conflicting records and require
                     an explicit winner before anything is persisted. --}}
        <p>{{ __('An active equivalency already exists for this course pair and direction. Choose which resolution prevails — the other is kept as Superseded, never discarded.') }}</p>

        <div class="form-field">
            <strong>{{ __('Existing resolution') }}</strong>
            <p>
                {{ __('Resolution :number', ['number' => $conflictingEquivalency->resolutionNumber()]) }}
                ({{ Str::headline($conflictingEquivalency->direction()->name) }})
            </p>
        </div>

        <div class="form-field">
            <strong>{{ __('New submission') }}</strong>
            <p>
                {{ __('Resolution :number', ['number' => $form->resolutionNumber]) }}
                ({{ $form->direction !== '' ? Str::headline(\App\Enums\EquivalencyDirection::from($form->direction)->name) : '' }})
            </p>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="resolveContradiction('existing')" wire:loading.attr="disabled" wire:target="resolveContradiction">{{ __('Keep existing resolution') }}</button>
            <button type="button" class="btn btn-primary" wire:click="resolveContradiction('candidate')" wire:loading.attr="disabled" wire:target="resolveContradiction">{{ __('Use new resolution') }}</button>
        </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
