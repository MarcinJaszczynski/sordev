<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    @php($summary = $this->summary())

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-gray-900">{{ $heading }}</p>
            <p class="mt-1 text-xs text-gray-500">
                Plan: <span class="font-medium text-gray-800">{{ $summary['planned'] }}</span>
                · Wpłacono: <span class="font-medium text-gray-800">{{ $summary['paid'] }}</span>
                · Pozostało: <span class="font-medium text-gray-800">{{ $summary['remaining'] }}</span>
                · <span class="font-medium">{{ $summary['status'] }}</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            {{ $this->planAction }}
            {{ $this->advanceAction }}
            {{ $this->paymentsAction }}
        </div>
    </div>

    @if ($summary['planned'] === '—')
        <p class="mt-3 text-xs text-amber-700">
            Brak pozycji kosztu w rozliczeniu — uzupełnij dane transportu / plan hotelu i zapisz, aby wygenerować kosztorys.
        </p>
    @endif

    <x-filament-actions::modals />
</div>
