<x-filament-panels::page>
    @include('filament.components.finance-module-nav', ['tabs' => $this->getNavigationTabs()])

    <form wire:submit.prevent="$refresh" class="mb-6">
        {{ $this->form }}

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button type="submit">Filtruj</x-filament::button>
            <x-filament::button color="gray" tag="a" href="#" wire:click.prevent="exportCsv">
                Eksport CSV
            </x-filament::button>
        </div>
    </form>

    @php($summary = $this->getReportSummary())
    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Operacje</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['count'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Wpływy</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($summary['amount_in'], 2, ',', ' ') }} PLN</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Wydatki</div>
            <div class="mt-1 text-2xl font-bold text-red-700">{{ number_format($summary['amount_out'], 2, ',', ' ') }} PLN</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Saldo</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['balance'], 2, ',', ' ') }} PLN</div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="admin-zebra-table min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Typ</th>
                    <th class="px-3 py-2 text-left">Opis</th>
                    <th class="px-3 py-2 text-left">Kontrahent</th>
                    <th class="px-3 py-2 text-left">Impreza</th>
                    <th class="px-3 py-2 text-right">Kwota</th>
                    <th class="px-3 py-2 text-left">Status</th>
                    <th class="px-3 py-2 text-left">Data</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->getReportRows() as $row)
                    <tr>
                        <td class="px-3 py-2">
                            <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">{{ $row['operation_label'] }}</span>
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-900">{{ $row['label'] }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['counterparty'] ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['event_code'] ?: '—' }}</td>
                        <td class="px-3 py-2 text-right font-semibold {{ $row['direction'] === 'in' ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $row['direction'] === 'in' ? '+' : '−' }}{{ number_format($row['amount_pln'], 2, ',', ' ') }}
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['status'] }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['operation_date'] ? \Carbon\Carbon::parse($row['operation_date'])->format('d.m.Y') : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-gray-500">Brak operacji dla wybranych filtrów.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
