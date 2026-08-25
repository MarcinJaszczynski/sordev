<div @class([
    'rounded-xl border border-gray-200 bg-white p-4 shadow-sm' => $this->aggregateType !== 'transport',
    'transport-finance-shell' => $this->aggregateType === 'transport',
])>
    @php($summary = $this->summary())

    @if ($this->aggregateType === 'transport')
        <style>
            .transport-finance-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
            }

            .transport-finance-stat-card {
                background: #F1EFE8;
                border-radius: 8px;
                padding: 12px;
            }

            .transport-finance-stat-card--danger {
                background: #FCEBEB;
            }

            .transport-finance-stat-title {
                margin: 0 0 4px;
                font-size: 12px;
                font-weight: 400;
                color: #5F5E5A;
            }

            .transport-finance-stat-card--danger .transport-finance-stat-title {
                color: #791F1F;
            }

            .transport-finance-stat-value {
                margin: 0;
                font-size: 18px;
                font-weight: 500;
                color: #2C2C2A;
            }

            .transport-finance-stat-card--danger .transport-finance-stat-value {
                color: #791F1F;
            }

            .transport-finance-actions-card {
                background: #F1EFE8;
                border-radius: 8px;
                padding: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .transport-finance-btn {
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                border: 1px solid #D3D1C7;
                background: #FFFFFF;
                color: #2C2C2A;
                padding: 0.4rem 0.75rem;
                line-height: 1.25;
            }

            .transport-finance-btn:hover {
                background: #E5E3DA;
            }

            .transport-finance-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            @media (max-width: 720px) {
                .transport-finance-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
        </style>

        <div class="mb-2">
            <p class="text-sm font-semibold" style="color:#2C2C2A">{{ $heading }}</p>
            <p class="text-xs" style="color:#5F5E5A">{{ $summary['status'] }} — plan, zaliczka i kolejne wpłaty w panelu bocznym.</p>
        </div>

        <div class="transport-finance-grid">
            <div class="transport-finance-stat-card">
                <p class="transport-finance-stat-title">Planowane</p>
                <p class="transport-finance-stat-value">{{ $summary['planned'] }}</p>
            </div>

            <div class="transport-finance-stat-card">
                <p class="transport-finance-stat-title">Zapłacono</p>
                <p class="transport-finance-stat-value">{{ $summary['paid'] }}</p>
            </div>

            <div class="transport-finance-stat-card transport-finance-stat-card--danger">
                <p class="transport-finance-stat-title">Pozostało</p>
                <p class="transport-finance-stat-value">{{ $summary['remaining'] }}</p>
            </div>

            <div class="transport-finance-actions-card">
                <div class="flex flex-wrap gap-2 justify-center">
                    <button type="button" class="transport-finance-btn" wire:click="openAggregateFinance('plan')">
                        Plan
                    </button>
                    <button type="button" class="transport-finance-btn" wire:click="openAggregateFinance('advance')">
                        Zaliczka
                    </button>
                    <button type="button" class="transport-finance-btn" wire:click="openAggregateFinance('payment')">
                        Wpłaty
                    </button>
                </div>
            </div>
        </div>

        @if ($summary['planned'] === '—')
            <p class="mt-3 text-xs" style="color:#633806">
                Brak pozycji kosztu w rozliczeniu — uzupełnij dane transportu / plan hotelu i zapisz, aby wygenerować kosztorys.
            </p>
        @endif
    @else
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-gray-900">{{ $heading }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    Planowane: <span class="font-medium text-gray-800">{{ $summary['planned'] }}</span>
                    · Zapłacono: <span class="font-medium text-gray-800">{{ $summary['paid'] }}</span>
                    · Pozostało: <span class="font-medium text-gray-800">{{ $summary['remaining'] }}</span>
                    · <span class="font-medium">{{ $summary['status'] }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-filament::button size="sm" color="warning" wire:click="openAggregateFinance('plan')" icon="heroicon-o-banknotes">
                    Plan
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="openAggregateFinance('advance')" icon="heroicon-o-credit-card">
                    Zaliczka
                </x-filament::button>
                <x-filament::button size="sm" color="success" wire:click="openAggregateFinance('payment')" icon="heroicon-o-receipt-percent">
                    Wpłaty
                </x-filament::button>
            </div>
        </div>

        @if ($summary['planned'] === '—')
            <p class="mt-3 text-xs text-amber-700">
                Brak pozycji kosztu w rozliczeniu — uzupełnij dane transportu / plan hotelu i zapisz, aby wygenerować kosztorys.
            </p>
        @endif
    @endif

    @include('filament.resources.event-resource.pages.partials.event-finance-cost-drawer')
</div>
