<x-filament-panels::page>
    @include('filament.components.executive-module-nav', ['activeTab' => 'profit-loss', 'tabs' => $this->getNavigationTabs()])

    <form wire:submit.prevent="$refresh" class="mb-6">
        {{ $this->form }}

        <div class="mt-3 flex flex-wrap gap-2">
            <span class="self-center text-xs text-gray-500">Szybkie statusy:</span>
            <x-filament::button size="sm" color="gray" wire:click="presetStatusesWithoutCancelled">
                Bez anulowanych
            </x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="presetStatusesPipeline">
                Oferta + rezerwacja + potwierdzona
            </x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="presetStatusesAll">
                Wszystkie statusy
            </x-filament::button>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button type="submit">Filtruj</x-filament::button>
            @if($this->hasActiveNarrowing())
                <x-filament::button color="gray" wire:click="resetNarrowing">
                    Reset filtrów
                </x-filament::button>
            @endif
            <x-filament::button color="gray" tag="a" href="#" wire:click.prevent="exportCsv">
                Eksport CSV
            </x-filament::button>
            <x-filament::button color="gray" tag="a" :href="$this->eventsIndexUrl()" target="_blank">
                Lista imprez
            </x-filament::button>
            <x-filament::button color="gray" tag="a" :href="$this->contractorsUrl()" target="_blank">
                Kontrahenci
            </x-filament::button>
        </div>
    </form>

    @php($summary = $this->getSummary())
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Przychód uznany</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['revenue_pln'], 2, ',', ' ') }} PLN</div>
            <div class="mt-1 text-xs text-gray-500">
                Plan (due): {{ number_format($summary['revenue_due_pln'], 2, ',', ' ') }} ·
                Zapłacono: {{ number_format($summary['revenue_paid_pln'], 2, ',', ' ') }}
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Koszt uznany</div>
            <div class="mt-1 text-2xl font-bold text-red-700">{{ number_format($summary['costs_pln'], 2, ',', ' ') }} PLN</div>
            <div class="mt-1 text-xs text-gray-500">
                Plan: {{ number_format($summary['cost_planned_pln'], 2, ',', ' ') }} ·
                Zapłacono: {{ number_format($summary['cost_paid_pln'], 2, ',', ' ') }} ·
                Do zapłaty: {{ number_format($summary['cost_outstanding_pln'], 2, ',', ' ') }}
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Zysk uznany</div>
            <div class="mt-1 text-2xl font-bold {{ $summary['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ number_format($summary['net_result_pln'], 2, ',', ' ') }} PLN
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Marża:
                {{ $summary['avg_margin_percent'] !== null ? number_format($summary['avg_margin_percent'], 1, ',', ' ').' %' : '—' }}
                · Imprez: {{ $summary['events'] }}
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Kasowo / salda</div>
            <div class="mt-1 text-sm text-gray-700">
                Bilans kasowy: <strong>{{ number_format($summary['cash_balance_pln'], 2, ',', ' ') }}</strong> PLN<br>
                Należności: <strong>{{ number_format($summary['receivables_pln'], 2, ',', ' ') }}</strong> PLN<br>
                Zobowiązania FV: <strong>{{ number_format($summary['payables_pln'], 2, ',', ' ') }}</strong> PLN
            </div>
        </div>
    </div>

    <div class="mb-2 text-xs text-gray-500">Kliknij wiersz w podsumowaniach, żeby zawęzić listę. Ponowne kliknięcie usuwa zawężenie.</div>

    <div class="mb-6 grid gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Wg fazy</h3>
            <div class="space-y-2 text-sm">
                @forelse($this->getByPhase() as $row)
                    <button
                        type="button"
                        wire:click="narrowPhase('{{ $row['phase'] }}')"
                        class="flex w-full items-center justify-between gap-2 rounded-lg border border-transparent px-2 py-2 text-left transition hover:border-primary-200 hover:bg-primary-50 {{ $filterPhase === $row['phase'] ? 'border-primary-300 bg-primary-50' : 'border-b border-gray-100' }}"
                    >
                        <div>
                            <div class="font-medium text-gray-900">{{ $row['label'] }}</div>
                            <div class="text-xs text-gray-500">{{ $row['events'] }} imprez · kliknij aby zawęzić</div>
                        </div>
                        <div class="text-right font-semibold {{ $row['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($row['net_result_pln'], 2, ',', ' ') }}
                        </div>
                    </button>
                @empty
                    <div class="text-gray-500">Brak danych.</div>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Top szablony</h3>
            <div class="space-y-2 text-sm">
                @forelse(array_slice($this->getByTemplate(), 0, 8) as $row)
                    @php($templateKey = $row['key'] !== null ? (string) $row['key'] : 'none')
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2 {{ (string) $filterTemplateId === $templateKey ? 'rounded-lg bg-primary-50 px-2' : '' }}">
                        <div class="min-w-0">
                            <button type="button" wire:click="narrowTemplate('{{ $templateKey }}')" class="text-left font-medium text-primary-700 hover:underline">
                                {{ $row['label'] }}
                            </button>
                            <div class="text-xs text-gray-500">
                                {{ $row['events'] }} ·
                                {{ $row['margin_percent'] !== null ? number_format($row['margin_percent'], 1, ',', ' ').'%' : '—' }}
                                @if($row['key'])
                                    ·
                                    <a href="{{ $this->templateUrl((int) $row['key']) }}" target="_blank" class="text-gray-500 hover:text-primary-700 hover:underline">
                                        otwórz szablon
                                    </a>
                                    ·
                                    <a href="{{ $this->eventsIndexUrl(templateId: (string) $row['key']) }}" target="_blank" class="text-gray-500 hover:text-primary-700 hover:underline">
                                        imprezy
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right font-semibold {{ $row['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($row['net_result_pln'], 2, ',', ' ') }}
                        </div>
                    </div>
                @empty
                    <div class="text-gray-500">Brak danych.</div>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">Top klienci</h3>
            <div class="space-y-2 text-sm">
                @forelse(array_slice($this->getByClient(), 0, 8) as $row)
                    @php($isActiveClient = ($row['label'] === 'Bez klienta' && $filterClient === '__none__') || ($filterClient === $row['label']))
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-2 {{ $isActiveClient ? 'rounded-lg bg-primary-50 px-2' : '' }}">
                        <div class="min-w-0">
                            <button type="button" wire:click="narrowClient(@js($row['label']))" class="text-left font-medium text-primary-700 hover:underline">
                                {{ $row['label'] }}
                            </button>
                            <div class="text-xs text-gray-500">
                                {{ $row['events'] }} ·
                                {{ $row['margin_percent'] !== null ? number_format($row['margin_percent'], 1, ',', ' ').'%' : '—' }}
                                @if($row['label'] !== 'Bez klienta')
                                    ·
                                    <a href="{{ $this->eventsIndexUrl(search: $row['label']) }}" target="_blank" class="text-gray-500 hover:text-primary-700 hover:underline">
                                        imprezy klienta
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 text-right font-semibold {{ $row['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($row['net_result_pln'], 2, ',', ' ') }}
                        </div>
                    </div>
                @empty
                    <div class="text-gray-500">Brak danych.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
        <h3 class="mb-4 text-sm font-semibold text-gray-900">Trend miesięczny (zysk uznany)</h3>
        <canvas id="executive-pl-trend" height="100" wire:ignore></canvas>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="admin-zebra-table min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">
                        <button type="button" wire:click="sortByColumn('event_name')" class="font-semibold hover:text-primary-700">
                            Impreza{{ $this->sortIndicator('event_name') }}
                        </button>
                        /
                        <button type="button" wire:click="sortByColumn('event_date')" class="font-semibold hover:text-primary-700">
                            data{{ $this->sortIndicator('event_date') }}
                        </button>
                        /
                        <button type="button" wire:click="sortByColumn('client_name')" class="font-semibold hover:text-primary-700">
                            klient{{ $this->sortIndicator('client_name') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-left">
                        <button type="button" wire:click="sortByColumn('phase')" class="font-semibold hover:text-primary-700">
                            Faza / uznanie{{ $this->sortIndicator('phase') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" wire:click="sortByColumn('revenue_pln')" class="font-semibold hover:text-primary-700">
                            Przychód{{ $this->sortIndicator('revenue_pln') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" wire:click="sortByColumn('costs_pln')" class="font-semibold hover:text-primary-700">
                            Koszt{{ $this->sortIndicator('costs_pln') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" wire:click="sortByColumn('net_result_pln')" class="font-semibold hover:text-primary-700">
                            Zysk{{ $this->sortIndicator('net_result_pln') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" wire:click="sortByColumn('cost_outstanding_pln')" class="font-semibold hover:text-primary-700">
                            Do zapłaty{{ $this->sortIndicator('cost_outstanding_pln') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">
                        <button type="button" wire:click="sortByColumn('receivables_pln')" class="font-semibold hover:text-primary-700">
                            Należności{{ $this->sortIndicator('receivables_pln') }}
                        </button>
                    </th>
                    <th class="px-3 py-2 text-right">Akcje</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->getDisplayRows() as $row)
                    <tr>
                        <td class="px-3 py-2">
                            @if(!empty($row['event_id']))
                                <a href="{{ $this->eventFinanceUrl((int) $row['event_id']) }}" class="font-medium text-primary-700 hover:underline">
                                    {{ $row['event_name'] }}
                                </a>
                            @else
                                <div class="font-medium text-gray-900">{{ $row['event_name'] }}</div>
                            @endif
                            <div class="text-xs text-gray-500">
                                {{ $row['event_code'] }} ·
                                {{ $row['event_date'] ? \Carbon\Carbon::parse($row['event_date'])->format('d.m.Y') : '—' }}
                                @if(!empty($row['client_name']))
                                    ·
                                    <button type="button" wire:click="narrowClient(@js($row['client_name']))" class="hover:text-primary-700 hover:underline">
                                        {{ $row['client_name'] }}
                                    </button>
                                @endif
                            </div>
                            @if(!empty($row['template_name']))
                                <div class="text-xs text-gray-400">
                                    @if(!empty($row['template_id']))
                                        <button type="button" wire:click="narrowTemplate('{{ $row['template_id'] }}')" class="hover:text-primary-700 hover:underline">
                                            {{ $row['template_name'] }}
                                        </button>
                                        ·
                                        <a href="{{ $this->templateUrl((int) $row['template_id']) }}" target="_blank" class="hover:text-primary-700 hover:underline">szablon</a>
                                    @else
                                        {{ $row['template_name'] }}
                                    @endif
                                </div>
                            @endif
                            @if(!empty($row['from_offer_fallback']))
                                <div class="text-xs text-amber-600">Źródło: kalkulacja oferty</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">
                            <button type="button" wire:click="narrowPhase('{{ $row['phase'] }}')" class="hover:text-primary-700 hover:underline">
                                {{ $row['phase_label'] ?? '—' }}
                            </button>
                            <div class="text-xs text-gray-400">{{ $row['recognition_mode_label'] ?? '' }}</div>
                            <div class="text-xs text-gray-400">{{ $row['status_label'] ?? '' }}</div>
                        </td>
                        <td class="px-3 py-2 text-right text-emerald-700">
                            {{ number_format($row['revenue_pln'], 2, ',', ' ') }}
                            <div class="text-xs text-gray-400">
                                due {{ number_format($row['revenue_due_pln'] ?? 0, 2, ',', ' ') }} /
                                paid {{ number_format($row['revenue_paid_pln'] ?? 0, 2, ',', ' ') }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right text-red-700">
                            {{ number_format($row['costs_pln'], 2, ',', ' ') }}
                            <div class="text-xs text-gray-400">
                                plan {{ number_format($row['cost_planned_pln'] ?? 0, 2, ',', ' ') }} /
                                paid {{ number_format($row['cost_paid_pln'] ?? 0, 2, ',', ' ') }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-right font-semibold {{ $row['net_result_pln'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ number_format($row['net_result_pln'], 2, ',', ' ') }}
                            @if(($row['margin_recognized_percent'] ?? null) !== null)
                                <div class="text-xs font-normal text-gray-500">{{ number_format($row['margin_recognized_percent'], 1, ',', ' ') }}%</div>
                            @endif
                            @if((float) ($row['offer_markup_pln'] ?? 0) > 0.009 || (float) ($row['offer_tax_pln'] ?? 0) > 0.009)
                                <div class="mt-1 space-y-0.5 text-[10px] font-normal leading-tight text-gray-500">
                                    <div>Narzut (zysk): {{ number_format((float) ($row['offer_markup_pln'] ?? 0), 2, ',', ' ') }}
                                        @if((float) ($row['offer_markup_percent'] ?? 0) > 0)
                                            ({{ number_format((float) $row['offer_markup_percent'], 1, ',', ' ') }}%)
                                        @endif
                                    </div>
                                    <div>Podatki: {{ number_format((float) ($row['offer_tax_pln'] ?? 0), 2, ',', ' ') }}</div>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-amber-700">{{ number_format($row['cost_outstanding_pln'] ?? 0, 2, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right text-gray-600">{{ number_format($row['receivables_pln'], 2, ',', ' ') }}</td>
                        <td class="px-3 py-2 text-right text-xs">
                            @if(!empty($row['event_id']))
                                <div class="flex flex-col items-end gap-1">
                                    <a href="{{ $this->eventFinanceUrl((int) $row['event_id']) }}" class="text-primary-700 hover:underline">Finanse</a>
                                    <a href="{{ $this->eventEditUrl((int) $row['event_id']) }}" class="text-gray-500 hover:underline">Edycja</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">Brak danych dla wybranych filtrów.</td>
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
                                label: 'Zysk uznany (PLN)',
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
