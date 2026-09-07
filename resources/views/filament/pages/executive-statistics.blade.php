<x-filament-panels::page>
    @include('filament.components.executive-module-nav', ['activeTab' => 'statistics', 'tabs' => $this->getNavigationTabs()])

    <div class="mb-4 flex flex-wrap gap-3">
        <x-filament::button tag="a" :href="$this->profitLossUrl()" color="primary">
            Otwórz Zyski i straty
        </x-filament::button>
        <x-filament::button tag="a" :href="$this->eventsIndexUrl()" color="gray">
            Lista imprez
        </x-filament::button>
        <x-filament::button tag="a" :href="$this->contractorsUrl()" color="gray">
            Kontrahenci
        </x-filament::button>
    </div>

    @php($overview = $this->getOverview())
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $this->eventsIndexUrl() }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Imprezy łącznie</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $overview['events_total'] }}</div>
            <div class="mt-1 text-xs text-primary-700">Przejdź do listy →</div>
        </a>
        <a href="{{ $this->eventsIndexUrl('confirmed') }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">W realizacji</div>
            <div class="mt-1 text-2xl font-bold text-primary-700">{{ $overview['events_active'] }}</div>
            <div class="mt-1 text-xs text-primary-700">Filtr: potwierdzone →</div>
        </a>
        <a href="{{ $this->profitLossUrl(['settlement_status' => 'active']) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Otwarte rozliczenia</div>
            <div class="mt-1 text-2xl font-bold text-amber-700">{{ $overview['open_settlements'] }}</div>
            <div class="mt-1 text-xs text-primary-700">Zobacz w P&L →</div>
        </a>
        <a href="{{ $this->profitLossUrl(['phase' => 'future']) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Uczestnicy (nadchodzące)</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $overview['participants_upcoming'] }}</div>
            <div class="mt-1 text-xs text-primary-700">Przyszłe w P&L →</div>
        </a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ $this->profitLossUrl(['from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Zysk uznany (rok kalendarzowy)</div>
            <div class="mt-1 text-2xl font-bold {{ ($overview['year_recognized_margin_pln'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($overview['year_recognized_margin_pln'] ?? 0, 2, ',', ' ') }} PLN
            </div>
            <div class="mt-1 text-xs text-gray-500">{{ $overview['year_events_in_pl'] ?? 0 }} imprez · otwórz P&L roku →</div>
        </a>
        <a href="{{ $this->profitLossUrl(['phase' => 'future', 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Przyszłe (zysk planowany)</div>
            <div class="mt-1 text-xl font-bold text-gray-900">{{ number_format($overview['future_margin_pln'] ?? 0, 2, ',', ' ') }} PLN</div>
            <div class="mt-1 text-xs text-primary-700">Zawęż P&L do przyszłych →</div>
        </a>
        <a href="{{ $this->profitLossUrl(['phase' => 'in_progress', 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">W trakcie (blend)</div>
            <div class="mt-1 text-xl font-bold text-gray-900">{{ number_format($overview['in_progress_margin_pln'] ?? 0, 2, ',', ' ') }} PLN</div>
            <div class="mt-1 text-xs text-primary-700">Zawęż P&L do trwających →</div>
        </a>
        <a href="{{ $this->profitLossUrl(['phase' => 'completed', 'from' => now()->startOfYear()->toDateString(), 'to' => now()->endOfYear()->toDateString()]) }}" class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Zakończone (uznane)</div>
            <div class="mt-1 text-xl font-bold text-gray-900">{{ number_format($overview['completed_margin_pln'] ?? 0, 2, ',', ' ') }} PLN</div>
            <div class="mt-1 text-xs text-primary-700">Zawęż P&L do zakończonych →</div>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">Imprezy wg statusu</h3>
            <canvas id="executive-events-status" height="180"></canvas>
            <div class="mt-4 space-y-2 text-sm">
                @foreach($this->getEventsByStatus() as $item)
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
                        <a href="{{ $this->eventsIndexUrl($item['status']) }}" class="font-medium text-primary-700 hover:underline">
                            {{ $item['label'] }}
                        </a>
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-gray-900">{{ $item['count'] }}</span>
                            <a href="{{ $this->profitLossUrl(['statuses' => [$item['status']]]) }}" class="text-xs text-gray-500 hover:text-primary-700 hover:underline">
                                P&L
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">Wyjazdy miesięcznie</h3>
            <canvas id="executive-events-monthly" height="180"></canvas>
            <div class="mt-4 space-y-2 text-sm">
                @foreach($this->getEventsPerMonth() as $item)
                    @php
                        $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $item['month'])->startOfMonth()->toDateString();
                        $monthEnd = \Carbon\Carbon::createFromFormat('Y-m', $item['month'])->endOfMonth()->toDateString();
                    @endphp
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2">
                        <a href="{{ $this->profitLossUrl(['from' => $monthStart, 'to' => $monthEnd]) }}" class="font-medium text-primary-700 hover:underline">
                            {{ $item['label'] }}
                        </a>
                        <span class="font-semibold text-gray-900">{{ $item['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Rozliczenia wg statusu</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($this->getSettlementsByStatus() as $item)
                <a href="{{ $this->profitLossUrl(['settlement_status' => $item['status']]) }}" class="rounded-lg bg-gray-50 px-4 py-3 transition hover:bg-primary-50">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $item['label'] }}</div>
                    <div class="mt-1 text-xl font-bold text-gray-900">{{ $item['count'] }}</div>
                    <div class="mt-1 text-xs text-primary-700">Zobacz w P&L →</div>
                </a>
            @endforeach
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Chart === 'undefined') return;

                const byStatus = @json($this->getEventsByStatus());
                const statusCanvas = document.getElementById('executive-events-status');
                if (statusCanvas) {
                    new Chart(statusCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: byStatus.map(item => item.label),
                            datasets: [{
                                data: byStatus.map(item => item.count),
                                backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#64748b', '#ef4444'],
                            }],
                        },
                        options: { plugins: { legend: { position: 'bottom' } } },
                    });
                }

                const monthly = @json($this->getEventsPerMonth());
                const monthlyCanvas = document.getElementById('executive-events-monthly');
                if (monthlyCanvas) {
                    new Chart(monthlyCanvas, {
                        type: 'line',
                        data: {
                            labels: monthly.map(item => item.label),
                            datasets: [{
                                label: 'Liczba imprez',
                                data: monthly.map(item => item.count),
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.15)',
                                fill: true,
                                tension: 0.3,
                            }],
                        },
                        options: {
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        },
                    });
                }
            });
        </script>
    @endpush
</x-filament-panels::page>
