<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        $financials = $this->financials();
        $keyInfo = $this->keyInfo();
        $shortcuts = $this->shortcuts();
    @endphp

    @if (count($shortcuts))
        <div class="mb-6 sticky top-0 z-10 -mx-4 px-4 py-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-2">
                @foreach ($shortcuts as $shortcut)
                    <x-filament::button
                        tag="a"
                        :href="$shortcut['url']"
                        :icon="$shortcut['icon']"
                        color="gray"
                        size="sm"
                    >
                        {{ $shortcut['label'] }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>
    @endif

    @capture($form)
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @endcapture

    @php
        $relationManagers = $this->getRelationManagers();
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp

    @if (! $hasCombinedRelationManagerTabsWithContent || ! count($relationManagers))
        {{ $form() }}
    @endif

    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager"
            :content-tab-label="$this->getContentTabLabel()"
            :content-tab-icon="$this->getContentTabIcon()"
            :content-tab-position="$this->getContentTabPosition()"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        >
            @if ($hasCombinedRelationManagerTabsWithContent)
                <x-slot name="content">
                    {{ $form() }}
                </x-slot>
            @endif
        </x-filament-panels::resources.relation-managers>
    @endif

    <div class="space-y-6 mt-8">
        {{-- Notatki imprezy --}}
        <x-filament::section>
            <x-slot name="heading">Notatki zespołowe</x-slot>
            <x-slot name="description">Ustalenia operacyjne widoczne dla całego zespołu — najnowsza na górze stosu.</x-slot>
            @include('filament.components.sticky-notes-stack', [
                'notableType' => \App\Models\Event::class,
                'notableId' => $this->record->id,
                'title' => 'Notatki imprezy',
            ])
        </x-filament::section>

        {{-- Finanse — skrót do zakładki workflow --}}
        <x-filament::section>
            <x-slot name="heading">Finanse</x-slot>
            <x-slot name="description">Szczegóły rozliczenia i wpłat w dedykowanej zakładce workflow.</x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\EventResource::getUrl('calculation', ['record' => $record])"
                    icon="heroicon-o-banknotes"
                    color="gray"
                >
                    Otwórz zakładkę Finanse
                </x-filament::button>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Kalkulacja: {{ $financials['calc'] }} · Wpłaty klientów: {{ $financials['clients_paid'] }}
                </span>
            </div>
        </x-filament::section>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
