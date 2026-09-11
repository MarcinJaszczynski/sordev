<div class="fi-task-full-editor space-y-4">
    @if ($record->exists && $record->parent_id)
        @php
            $record->loadMissing('parent');
            $parent = $record->parent;
            $parentUrl = $parent
                ? \App\Support\Tasks\TaskNavigation::fullViewUrl($parent)
                : null;
        @endphp
        @if ($parent)
            <div class="flex flex-col gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5 dark:border-indigo-500/40 dark:bg-indigo-500/10 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="text-[0.65rem] font-bold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                        Podzadanie
                    </div>
                    <div class="mt-0.5 truncate text-sm text-indigo-950 dark:text-indigo-100">
                        Nadrzędne:
                        <span class="font-semibold">{{ $parent->title }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button
                        type="button"
                        color="primary"
                        icon="heroicon-o-arrow-up-left"
                        wire:click="openParentTask"
                        size="sm"
                    >
                        Otwórz zadanie główne
                    </x-filament::button>
                    @if ($parentUrl)
                        <x-filament::button
                            tag="a"
                            :href="$parentUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            color="gray"
                            icon="heroicon-o-arrow-top-right-on-square"
                            size="sm"
                        >
                            W nowej karcie
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endif
    @endif

    @php
        $contextTask = \App\Support\Tasks\TaskContextRegistry::resolveEffectiveContextTask($record);
        if ($contextTask) {
            $contextTask->loadMissing('taskable');
        }
        $contextType = $contextTask?->taskable_type_label;
        $contextRecord = $contextTask?->taskable_label;
        $contextLinks = $this->contextLinks();
    @endphp

    @if ($contextType || $contextLinks !== [])
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            @if ($contextType)
                <div class="mb-2">
                    <div class="text-[0.65rem] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $contextType }}
                    </div>
                    <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-gray-100">
                        {{ $contextRecord }}
                    </div>
                </div>
            @endif
            @if ($contextLinks !== [])
                @include('filament.pages.partials.calendar-entry-links', [
                    'links' => $contextLinks,
                    'openInNewTab' => true,
                ])
            @endif
        </div>
    @endif

    {{--
        Div zamiast <form>: modal akcji Filament już owija treść w <form wire:submit="callMountedAction">.
        Zagnieżdżony formularz jest nielegalny w HTML (wewnętrzny </form> zamyka modal)
        i przycisk Zapisz zamykałby modal bez zapisu.
    --}}
    <div id="task-full-editor-form" class="fi-form grid gap-y-6">
        {{ $this->form }}

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            @if ($record->exists)
                <x-filament::button type="button" color="gray" wire:click="markAsUnread">
                    Oznacz jako nieprzeczytane
                </x-filament::button>
            @endif
            <x-filament::button type="button" wire:click="save">
                Zapisz
            </x-filament::button>
        </div>
    </div>

    @if ($record->exists)
        @php
            $managerLivewireProperties = [
                'ownerRecord' => $record,
                'pageClass' => \App\Filament\Resources\TaskResource\Pages\EditTask::class,
                'panelMode' => true,
            ];
            $attachmentsManager = \App\Filament\Resources\TaskResource\RelationManagers\AttachmentsRelationManager::class;
            $subtasksManager = \App\Filament\Resources\TaskResource\RelationManagers\SubtasksRelationManager::class;
        @endphp

        {{-- Komentarze pełna szerokość; załączniki + podzadania dwukolumnowo pod spodem. --}}
        <div class="fi-task-editor-panel fi-task-editor-panel--comments">
            @include('livewire.partials.task-editor-comments')
        </div>

        <div class="fi-task-editor-panels-row">
            <section
                wire:key="task-editor-attachments-{{ $record->getKey() }}"
                class="fi-task-editor-panel rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                @livewire(
                    $attachmentsManager,
                    $managerLivewireProperties,
                    key($attachmentsManager.'-attachments-'.$record->getKey()),
                )
            </section>

            <section
                wire:key="task-editor-subtasks-{{ $record->getKey() }}"
                class="fi-task-editor-panel rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                @livewire(
                    $subtasksManager,
                    $managerLivewireProperties,
                    key($subtasksManager.'-subtasks-'.$record->getKey()),
                )
            </section>
        </div>
    @endif

    <style>
        .fi-task-full-editor .fi-task-editor-panel--comments {
            margin-top: 0.25rem;
        }

        .fi-task-full-editor .fi-task-editor-panels-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            align-items: stretch;
            margin-top: 0.75rem;
        }

        @media (min-width: 768px) {
            .fi-task-full-editor .fi-task-editor-panels-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .fi-task-full-editor .fi-task-editor-panel {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-resource-relation-managers,
        .fi-task-full-editor .fi-task-editor-panel .fi-resource-relation-managers > div {
            min-width: 0;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-ctn {
            border: 0;
            box-shadow: none;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-header-ctn {
            padding-inline: 0.75rem;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .dark .fi-task-full-editor .fi-task-editor-panel .fi-ta-header-ctn {
            border-bottom-color: rgb(255 255 255 / 0.1);
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-header-toolbar {
            align-items: center;
            gap: 0.5rem;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-header-heading {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-content {
            overflow-x: auto;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-table {
            table-layout: fixed;
            width: 100%;
        }

        .fi-task-full-editor .fi-task-editor-panel .fi-ta-cell {
            word-break: break-word;
        }
    </style>
</div>
