<x-filament-panels::page>
    @include('filament.components.executive-module-nav', ['activeTab' => 'profit-loss', 'tabs' => $this->getNavigationTabs()])

    <form wire:submit.prevent="$refresh" class="mb-6">
        {{ $this->form }}

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button type="submit">Filtruj</x-filament::button>
            <x-filament::button color="gray" tag="a" href="#" wire:click.prevent="exportCsv">
                Eksport CSV
            </x-filament::button>
        </div>
    </form>

    @php($summary = $this->getSummary())
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Przychód</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['revenue_pln'], 2, ',', ' ') }} PLN</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Koszty</div>
            <div class="mt-1 text-2xl font-bold text-red-700">{{ number_format($summary['costs_pln'], 2, ',', ' ') }} PLN</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Wynik netto</div>
            <div class="mt-1 text-2xl font-bold {{ $summary['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($summary['net_result_pln'], 2, ',', ' ') }} PLN
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Bilans płatności</div>
            <div class="mt-1 text-sm text-gray-700">
                Należności: <strong>{{ number_format($summary['receivables_pln'], 2, ',', ' ') }}</strong> PLN<br>
                Zobowiązania: <strong>{{ number_format($summary['payables_pln'], 2, ',', ' ') }}</strong> PLN
            </div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Trend miesięczny (wynik netto)</h3>
        <canvas id="executive-pl-trend" height="100"></canvas>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="admin-zebra-table min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Impreza</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-right">Przychód</th>
                    <th class="px-3 py-2 text-right">Koszty</th>
                    <th class="px-3 py-2 text-right">Wynik</th>
                    <th class="px-3 py-2 text-right">Należności</th>
                    <th class="px-3 py-2 text-right">Zobowiązania</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->getRows() as $row)
                    <tr>
                        <td class="px-3 py-2">
                            <div class="font-medium text-gray-900">{{ $row['event_name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $row['event_code'] }} · {{ $row['event_date'] ? \Carbon\Carbon::parse($row['event_date'])->format('d.m.Y') : '—' }}</div>
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['status_label'] }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700">{{ number_format($row['revenue_pln'], 2, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right text-red-700">{{ number_format($row['costs_pln'], 2, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right font-semibold {{ $row['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($row['net_result_pln'], 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ number_format($row['receivables_pln'], 2, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ number_format($row['payables_pln'], 2, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">Brak danych dla wybranych filtrów.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const canvas = document.getElementById('executive-pl-trend');
                if (!canvas || typeof Chart === 'undefined') return;

                const trend = @json($this->getMonthlyTrend());

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: trend.map(item => item.label),
                        datasets: [
                            {
                                label: 'Wynik netto (PLN)',
                                data: trend.map(item => item.net),
                                backgroundColor: trend.map(item => item.net >= 0 ? 'rgba(16, 185, 129, 0.7)' : 'rgba(239, 68, 68, 0.7)'),
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } },
                    },
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
