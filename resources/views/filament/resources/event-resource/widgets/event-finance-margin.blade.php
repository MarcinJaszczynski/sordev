<div>
    @php
        $m = $this->margin;
    @endphp

    @if ($m !== [])
        <div class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500" title="Marża według planu kosztów i przychodu.">Marża planowana</p>
                <p class="text-lg font-semibold text-gray-900">{{ $m['labels']['planned_margin'] ?? '—' }}</p>
                <p class="text-xs text-gray-500">Przychód {{ $m['labels']['planned_revenue'] ?? '—' }} · koszt {{ $m['labels']['planned_cost'] ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500" title="Marża na podstawie rzeczywistych wpłat i kosztów.">Marża rzeczywista</p>
                <p class="text-lg font-semibold text-gray-900">{{ $m['labels']['actual_margin'] ?? '—' }}</p>
                <p class="text-xs text-gray-500">Przychód {{ $m['labels']['actual_revenue'] ?? '—' }} · koszt {{ $m['labels']['actual_cost'] ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500" title="Różnica między marżą rzeczywistą a planowaną.">Różnica marży</p>
                <p @class([
                    'text-lg font-semibold',
                    'text-emerald-700' => ($m['margin_delta'] ?? 0) >= 0,
                    'text-rose-700' => ($m['margin_delta'] ?? 0) < 0,
                ])>
                    {{ $m['labels']['margin_delta'] ?? '—' }}
                    @if (($m['margin_delta_percent'] ?? null) !== null)
                        <span class="text-sm font-normal">({{ $m['margin_delta_percent'] }}%)</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500" title="Koszty z niedopłatą lub po terminie płatności.">Zdrowie płatności kosztów</p>
                <p class="text-sm text-gray-800">
                    Niedopłaty: <strong>{{ $m['health_shortfalls'] ?? 0 }}</strong>
                    · Po terminie: <strong>{{ $m['health_overdue'] ?? 0 }}</strong>
                </p>
            </div>
        </div>
    @endif
</div>
