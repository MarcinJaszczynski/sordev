<div class="fi-task-full-editor space-y-4">
    @if ($this->contextLinks() !== [])
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            @include('filament.pages.partials.calendar-entry-links', [
                'links' => $this->contextLinks(),
                'openInNewTab' => true,
            ])
        </div>
    @endif

    @capture($form)
        <x-filament-panels::form
            id="task-full-editor-form"
            wire:submit="save"
        >
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">
                    {{ $record->exists ? 'Zapisz zadanie' : 'Utwórz zadanie' }}
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    @endcapture

    @php
        $relationManagers = $record->exists ? $this->getRelationManagers() : [];
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp

    @if ((! $hasCombinedRelationManagerTabsWithContent) || ($relationManagers === []))
        {{ $form() }}
    @endif

    @if ($relationManagers !== [])
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
            :content-tab-label="$this->getContentTabLabel()"
            :content-tab-icon="$this->getContentTabIcon()"
            :content-tab-position="$this->getContentTabPosition()"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="\App\Filament\Resources\TaskResource\Pages\EditTask::class"
        >
            @if ($hasCombinedRelationManagerTabsWithContent)
                <x-slot name="content">
                    {{ $form() }}
                </x-slot>
            @endif
        </x-filament-panels::resources.relation-managers>
    @endif
</div>
