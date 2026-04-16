<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── FILTRY ─────────────────────────────────────────────────────────── --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <div class="flex items-center justify-between gap-3">
                    <span class="font-bold text-base">Filtry</span>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        outlined
                        wire:click="resetLayoutState"
                    >
                        Resetuj układ
                    </x-filament::button>
                </div>
            </x-slot>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Od daty</label>
                    <input type="date" wire:model.lazy="selectedDateFrom"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary-500"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Do daty</label>
                    <input type="date" wire:model.lazy="selectedDateTo"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary-500"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <select wire:model.lazy="selectedStatus"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Wszystkie</option>
                        @foreach(\App\Models\Reservation::$statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Kontrahent</label>
                    <select wire:model.lazy="selectedContractor"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Wszyscy</option>
                        @foreach(\App\Models\Contractor::orderBy('name')->get() as $contractor)
                            <option value="{{ $contractor->id }}">{{ $contractor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Impreza</label>
                    <select wire:model.lazy="selectedEvent"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Wszystkie</option>
                        @foreach(\App\Models\Event::orderBy('name')->get() as $event)
                            <option value="{{ $event->id }}">{{ $event->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        {{-- ── KARTY STATYSTYK ────────────────────────────────────────────────── --}}
        @php
            $stats = $this->getStats();
            $statConfig = [
                ['bg' => 'bg-blue-600',   'dark' => 'dark:bg-blue-700',   'ring' => 'ring-blue-500/30'],
                ['bg' => 'bg-emerald-600','dark' => 'dark:bg-emerald-700','ring' => 'ring-emerald-500/30'],
                ['bg' => 'bg-amber-500',  'dark' => 'dark:bg-amber-600',  'ring' => 'ring-amber-400/30'],
                ['bg' => 'bg-red-600',    'dark' => 'dark:bg-red-700',    'ring' => 'ring-red-500/30'],
                ['bg' => 'bg-violet-600', 'dark' => 'dark:bg-violet-700', 'ring' => 'ring-violet-500/30'],
                ['bg' => 'bg-cyan-600',   'dark' => 'dark:bg-cyan-700',   'ring' => 'ring-cyan-500/30'],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
            @foreach($stats as $i => $stat)
                @php $cfg = $statConfig[$i] ?? $statConfig[0]; @endphp
                <div class="flex flex-col justify-between rounded-2xl p-5 shadow-lg ring-1 {{ $cfg['bg'] }} {{ $cfg['dark'] }} {{ $cfg['ring'] }} text-white">
                    <div class="flex items-center gap-2 mb-3">
                        @if($stat->getIcon())
                            <x-dynamic-component :component="$stat->getIcon()" class="w-5 h-5 opacity-80 shrink-0"/>
                        @endif
                        <span class="text-xs font-semibold uppercase tracking-wider opacity-80 leading-tight">{{ $stat->getLabel() }}</span>
                    </div>
                    <div class="text-3xl font-black leading-none tracking-tight mb-1">{{ $stat->getValue() }}</div>
                    @if($stat->getDescription())
                        <div class="text-xs opacity-70 mt-1">{{ $stat->getDescription() }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ── WYKRESY ─────────────────────────────────────────────────────────── --}}
        @php
            $dateChartData = $this->getReservationsByDateChartData();
            $statusLabels = \App\Models\Reservation::$statuses;
            $statusData = $this->getReservationsByStatusData();
            $contractorData = $this->getReservationsByContractorData();
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-bold text-base">Rezerwacje po dniach</span>
                </x-slot>
                <div class="relative" style="min-height:280px">
                    <canvas id="reservations-by-date-chart" style="max-height:320px"></canvas>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-bold text-base">Rezerwacje po statusie</span>
                </x-slot>
                <div class="relative" style="min-height:280px">
                    <canvas id="reservations-by-status-chart" style="max-height:320px"></canvas>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-bold text-base">Wartość rezerwacji po kontrahentach</span>
                </x-slot>
                <div class="relative" style="min-height:280px">
                    <canvas id="reservations-by-contractor-chart" style="max-height:320px"></canvas>
                </div>
            </x-filament::section>
        </div>

        {{-- ── TABELA REZERWACJI ────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="font-bold text-base">Rezerwacje</span>
            </x-slot>
            <div class="overflow-x-auto -mx-2">
                {{ $this->table }}
            </div>
        </x-filament::section>

    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            window.__reservationsAnalyticsData = {
                date: {
                    labels: @js($dateChartData['labels']),
                    counts: @js($dateChartData['counts']),
                    totals: @js($dateChartData['totals']),
                },
                status: {
                    labels: @js(array_keys($statusLabels)),
                    map: @js($statusLabels),
                    counts: @js(array_values($statusData)),
                },
                contractor: {
                    labels: @js(array_keys($contractorData)),
                    totals: @js(array_values($contractorData)),
                },
            };

            window.__reservationsAnalyticsCharts = window.__reservationsAnalyticsCharts || {};

            function initReservationsAnalyticsCharts() {
                if (typeof Chart === 'undefined') {
                    return;
                }

                const charts = window.__reservationsAnalyticsCharts;
                Object.values(charts).forEach((chart) => {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                });

                const data = window.__reservationsAnalyticsData || {};

                const dateCanvas = document.getElementById('reservations-by-date-chart');
                if (dateCanvas) {
                    charts.date = new Chart(dateCanvas, {
                        type: 'line',
                        data: {
                            labels: data.date?.labels ?? [],
                            datasets: [
                                {
                                    label: 'Liczba rezerwacji',
                                    data: data.date?.counts ?? [],
                                    yAxisID: 'y',
                                    borderColor: '#0ea5e9',
                                    backgroundColor: 'rgba(14,165,233,0.2)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                },
                                {
                                    label: 'Wartość (PLN)',
                                    data: data.date?.totals ?? [],
                                    yAxisID: 'y1',
                                    borderColor: '#16a34a',
                                    backgroundColor: 'rgba(22,163,74,0.12)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: false,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: {
                                    position: 'left',
                                    ticks: { precision: 0 },
                                },
                                y1: {
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: {
                                        callback: (value) => Number(value).toLocaleString('pl-PL') + ' PLN',
                                    },
                                },
                            },
                        },
                    });
                }

                const statusCanvas = document.getElementById('reservations-by-status-chart');
                if (statusCanvas) {
                    charts.status = new Chart(statusCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: (data.status?.labels ?? []).map((label) => data.status?.map?.[label] ?? label),
                            datasets: [
                                {
                                    data: data.status?.counts ?? [],
                                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#06b6d4', '#f97316', '#d946ef'],
                                    borderColor: 'transparent',
                                    hoverOffset: 12,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { padding: 16, font: { size: 12, weight: 'bold' } },
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => ' ' + ctx.label + ': ' + ctx.parsed,
                                    },
                                },
                            },
                        },
                    });
                }

                const contractorCanvas = document.getElementById('reservations-by-contractor-chart');
                if (contractorCanvas) {
                    charts.contractor = new Chart(contractorCanvas, {
                        type: 'bar',
                        data: {
                            labels: data.contractor?.labels ?? [],
                            datasets: [
                                {
                                    label: 'Wartość (PLN)',
                                    data: data.contractor?.totals ?? [],
                                    backgroundColor: 'rgba(59,130,246,0.85)',
                                    borderColor: '#1d4ed8',
                                    borderWidth: 2,
                                    borderRadius: 6,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: {
                                x: {
                                    ticks: {
                                        callback: (value) => Number(value).toLocaleString('pl-PL') + ' PLN',
                                        font: { size: 11 },
                                    },
                                    grid: { color: 'rgba(0,0,0,0.06)' },
                                },
                                y: { ticks: { font: { size: 11, weight: 'bold' } } },
                            },
                        },
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', initReservationsAnalyticsCharts);
            document.addEventListener('livewire:navigated', () => setTimeout(initReservationsAnalyticsCharts, 0));
        </script>
    @endpush
</x-filament-panels::page>
