<div class="fi-task-full-editor space-y-4">
    @if ($this->contextLinks() !== [])
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            @include('filament.pages.partials.calendar-entry-links', [
                'links' => $this->contextLinks(),
                'openInNewTab' => true,
            ])
        </div>
    @endif

    <x-filament-panels::form
        id="task-full-editor-form"
        wire:submit="save"
    >
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit">
                Zapisz
            </x-filament::button>
        </div>
    </x-filament-panels::form>

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

        <div class="fi-task-editor-panels-row">
            <div class="fi-task-editor-panel">
                @include('livewire.partials.task-editor-comments')
            </div>

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
        .fi-task-full-editor .fi-task-editor-panels-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            align-items: stretch;
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
