<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php
        $financials = $this->financials();
    @endphp

    <div class="mb-6 space-y-3">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
                <x-heroicon-m-check-badge class="h-6 w-6" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Status operacyjny imprezy</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Kliknij kartę, aby przejść do uzupełnienia</p>
            </div>
        </div>
        @include('filament.components.event-readiness-overview', ['event' => $record])
    </div>

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
            <x-slot name="heading">Finanse imprezy</x-slot>
            <x-slot name="description">Koszty (wykonanie), wpłaty uczestników i kalkulacja (plan) — jeden hub.</x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\EventResource::getUrl('finance', ['record' => $record])"
                    icon="heroicon-o-banknotes"
                    color="gray"
                >
                    Koszty
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\EventResource::getUrl('finance-participant-payments', ['record' => $record])"
                    icon="heroicon-o-credit-card"
                    color="gray"
                >
                    Wpłaty
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\EventResource::getUrl('calculation', ['record' => $record])"
                    icon="heroicon-o-calculator"
                    color="gray"
                >
                    Kalkulacja
                </x-filament::button>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Plan: {{ $financials['calc'] }}
                    · Za osobę: {{ $financials['per_person'] }}
                    · Wpłaty klientów: {{ $financials['clients_paid'] }}
                </span>
            </div>
        </x-filament::section>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
