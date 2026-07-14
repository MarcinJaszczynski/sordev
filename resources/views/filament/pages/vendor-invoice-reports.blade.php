<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'due' => 'Do zapłaty',
            'overdue' => 'Przeterminowane',
            'approved_unpaid' => 'Zaakceptowane nieopłacone',
            'paid_period' => 'Zapłacone w okresie',
        ] as $tab => $label)
            <x-filament::button
                :color="$activeTab === $tab ? 'primary' : 'gray'"
                wire:click="setTab('{{ $tab }}')"
            >
                {{ $label }}
            </x-filament::button>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
