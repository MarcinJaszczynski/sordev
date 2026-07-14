<x-filament-panels::page>
    @include('filament.components.executive-module-nav', ['activeTab' => 'statistics', 'tabs' => $this->getNavigationTabs()])

    @php($overview = $this->getOverview())
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Imprezy łącznie</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $overview['events_total'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">W realizacji</div>
            <div class="mt-1 text-2xl font-bold text-primary-700">{{ $overview['events_active'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Otwarte rozliczenia</div>
            <div class="mt-1 text-2xl font-bold text-amber-700">{{ $overview['open_settlements'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Uczestnicy (nadchodzące)</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $overview['participants_upcoming'] }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">Imprezy wg statusu</h3>
            <canvas id="executive-events-status" height="180"></canvas>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-4 text-sm font-semibold text-gray-900">Wyjazdy miesięcznie</h3>
            <canvas id="executive-events-monthly" height="180"></canvas>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Rozliczenia wg statusu</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($this->getSettlementsByStatus() as $item)
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $item['label'] }}</div>
                    <div class="mt-1 text-xl font-bold text-gray-900">{{ $item['count'] }}</div>
                </div>
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
