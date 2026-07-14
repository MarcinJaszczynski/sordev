@php
    $stages = $life['stages'];
    $recon = $life['reconciliation'];
    $resign = $life['resignations'];

    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ') . ' zł';
    $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ') . '%';
    $toneClass = [
        'success' => 'text-success-600 dark:text-success-400',
        'danger' => 'text-danger-600 dark:text-danger-400',
        'gray' => 'text-gray-600 dark:text-gray-300',
    ];

    $steps = [
        ['label' => 'Wstępna', 'icon' => 'heroicon-o-document-text', 'done' => $stages['preliminary']['complete']],
        ['label' => 'Przewidywana', 'icon' => 'heroicon-o-document-check', 'done' => $stages['predicted']['complete']],
        ['label' => 'Rzeczywista', 'icon' => 'heroicon-o-banknotes', 'done' => $stages['actual']['complete']],
        ['label' => 'Rozliczenie', 'icon' => 'heroicon-o-check-badge', 'done' => $recon['is_closed']],
    ];
@endphp

<div class="space-y-6">
    {{-- Stepper --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center">
            @foreach($steps as $step)
                <div class="flex flex-1 items-center {{ $loop->last ? 'flex-none' : '' }}">
                    <div class="flex flex-col items-center text-center">
                        <span @class([
                            'flex h-10 w-10 items-center justify-center rounded-full border-2',
                            'border-primary-500 bg-primary-500 text-white' => $step['done'],
                            'border-gray-300 bg-white text-gray-400 dark:border-white/20 dark:bg-white/5' => ! $step['done'],
                        ])>
                            <x-filament::icon :icon="$step['icon']" class="h-5 w-5" />
                        </span>
                        <span class="mt-2 text-xs font-medium text-gray-700 dark:text-gray-200">{{ $step['label'] }}</span>
                    </div>
                    @unless($loop->last)
                        <div @class([
                            'mx-2 h-0.5 flex-1',
                            'bg-primary-500' => $step['done'],
                            'bg-gray-200 dark:bg-white/10' => ! $step['done'],
                        ])></div>
                    @endunless
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
            Grupa: {{ $life['participant_count'] }} uczestników. Marża = przychód od klienta − koszt podwykonawców.
        </p>
    </div>



    {{-- Tabela porównawcza --}}
    <x-filament::section>
        <x-slot name="heading">Porównanie etapów</x-slot>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 font-medium">Pozycja</th>
                        <th class="px-3 py-2 text-right font-medium">Wstępna</th>
                        <th class="px-3 py-2 text-right font-medium">Przewidywana</th>
                        <th class="px-3 py-2 text-right font-medium">Rzeczywista</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($life['comparison'] as $row)
                        <tr class="border-t border-gray-100 dark:border-white/10">
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-200">{{ $row['label'] }}</td>
                            @foreach(['preliminary', 'predicted', 'actual'] as $col)
                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">
                                    {{ $row['type'] === 'percent' ? $pct($row['values'][$col]) : $money($row['values'][$col]) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Rozliczenie końcowe --}}
    <x-filament::section>
        <x-slot name="heading">Rozliczenie końcowe</x-slot>
        <x-slot name="description">Plan vs wykonanie. Wynik = wpłaty klientów − koszty rzeczywiste.</x-slot>
        <x-slot name="headerEnd">
            @if($settlementUrl)
                <x-filament::button tag="a" :href="$settlementUrl" size="sm" color="gray" icon="heroicon-o-calculator">
                    Otwórz rozliczenie
                </x-filament::button>
            @endif
        </x-slot>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Wynik (netto)</dt>
                <dd class="mt-1 text-sm font-bold {{ $recon['net_result_pln'] >= 0 ? $toneClass['success'] : $toneClass['danger'] }}">{{ $money($recon['net_result_pln']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Δ koszt (rzecz.−przew.)</dt>
                <dd class="mt-1 text-sm font-semibold {{ $recon['cost_delta_pln'] <= 0 ? $toneClass['success'] : $toneClass['danger'] }}">{{ $money($recon['cost_delta_pln']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Δ przychód (rzecz.−przew.)</dt>
                <dd class="mt-1 text-sm font-semibold {{ $recon['revenue_delta_pln'] >= 0 ? $toneClass['success'] : $toneClass['danger'] }}">{{ $money($recon['revenue_delta_pln']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Należność od klienta</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $money($recon['receivable_pln']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Status rozliczenia</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $recon['settlement_status_label'] ?? 'Brak' }}</dd>
            </div>
        </div>
    </x-filament::section>

    {{-- Rezygnacje --}}
    <x-filament::section>
        <x-slot name="heading">Rezygnacje uczestników</x-slot>
        <x-slot name="headerEnd">
            <x-filament::button tag="a" :href="$resignationsUrl" size="sm" color="gray" icon="heroicon-o-user-minus">
                Zarządzaj rezygnacjami
            </x-filament::button>
        </x-slot>

        @if($resign['has_any'])
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Liczba</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-white">{{ $resign['count'] }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Zwroty</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $money($resign['refund_pln']) }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Potrącenia</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $money($resign['retention_pln']) }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Wpłacono</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $money($resign['paid_pln']) }}</dd>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Brak zgłoszonych rezygnacji dla tej imprezy.</p>
        @endif
    </x-filament::section>
</div>
