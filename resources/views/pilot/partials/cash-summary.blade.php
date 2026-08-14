@php
    $editable = $editable ?? true;
    $compact = $compact ?? false;
    $isPilotContext = ($this->context ?? 'admin') === 'pilot';
@endphp

<div class="space-y-3">
    @if ($this->cashReconciliation->isEmpty())
        <p class="text-sm text-gray-500">
            Brak pozycji gotówki — pojawią się tu waluty z punktów programu (płatnik Pilot) oraz wypłat z biura.
        </p>
    @else
        @unless ($isPilotContext)
            <p class="text-xs text-gray-500">
                <strong>Do przygotowania</strong> = dopłata pilota gotówką: plan − zaliczki biura na kosztach
                (np. hotel 800 − zaliczka 300 = 500). Potem: wypłacono → wymiana → wydane → zwrot.
            </p>
        @else
            <p class="text-xs text-gray-500">
                Kwota od biura → ewentualna wymiana → wydatki → zwrot. Saldo per waluta.
            </p>
        @endunless

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-[10px] uppercase tracking-wide text-gray-500 dark:bg-gray-900">
                    <tr>
                        <th class="px-3 py-2 font-medium">Waluta</th>
                        @unless ($isPilotContext)
                            <th class="px-3 py-2 font-medium text-right">Do przygotowania</th>
                        @endunless
                        <th class="px-3 py-2 font-medium text-right">Od biura</th>
                        <th class="px-3 py-2 font-medium text-right">Po wymianie</th>
                        <th class="px-3 py-2 font-medium text-right">Wydane gotówką</th>
                        <th class="px-3 py-2 font-medium text-right">Saldo końcowe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->cashReconciliation as $row)
                        @php
                            $returnedInput = $editable && filled($this->cashReturned[$row->currency_id] ?? null)
                                ? (float) str_replace(',', '.', (string) $this->cashReturned[$row->currency_id])
                                : (float) $row->returned;
                            $available = (float) ($row->available ?? $row->office_provided);
                            $spent = (float) $row->actual_spent;
                            $remaining = round($available - $spent - $returnedInput, 2);
                            $toReturn = max(0, $remaining);
                            $cashShortfall = max(0, -$remaining);
                            $code = $row->currency_code ?? $row->currency_name;
                            $fromOffice = (float) ($row->from_office ?? $row->office_provided);
                            $needed = (float) ($row->needed ?? $row->calculated ?? 0);
                            $planTotal = (float) ($row->plan_total ?? $row->planned_expenses ?? 0);
                            $officeOnCosts = (float) ($row->office_advances_on_costs ?? 0);
                            $isTopUp = (bool) ($row->is_top_up ?? ($officeOnCosts > 0.009 && $needed > 0.009));
                            $exIn = (float) ($row->exchange_in ?? 0);
                            $exOut = (float) ($row->exchange_out ?? 0);
                            $negativeFloat = $available < -0.009;
                            $missingPayout = $needed > 0.009 && $fromOffice <= 0.009;
                        @endphp
                        <tr wire:key="pilot-cash-row-{{ $row->currency_id }}">
                            <td class="px-3 py-2.5 font-semibold text-gray-900 dark:text-gray-100">{{ $code }}</td>
                            @unless ($isPilotContext)
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    <div class="font-semibold text-sky-800 dark:text-sky-200">
                                        {{ number_format($needed, 2, ',', ' ') }}
                                    </div>
                                    @if ($isTopUp)
                                        <div class="text-[11px] font-medium text-amber-800 dark:text-amber-200">dopłata do kosztów</div>
                                    @endif
                                    @if ($planTotal > 0.009)
                                        <div class="text-[11px] text-gray-500">
                                            plan {{ number_format($planTotal, 2, ',', ' ') }}
                                            @if ($officeOnCosts > 0.009)
                                                − zal. {{ number_format($officeOnCosts, 2, ',', ' ') }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endunless
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <div class="font-semibold text-indigo-800 dark:text-indigo-200">
                                    {{ number_format($fromOffice, 2, ',', ' ') }}
                                </div>
                                @if (! $isPilotContext && $missingPayout)
                                    <div class="text-[11px] text-amber-800 dark:text-amber-200">wypłać z wyliczenia</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <div @class(['font-medium', 'text-red-700 dark:text-red-300' => $negativeFloat])>
                                    {{ number_format($available, 2, ',', ' ') }}
                                </div>
                                @if ($exIn > 0.009 || $exOut > 0.009)
                                    <div class="text-[11px] text-gray-500">
                                        @if ($exOut > 0.009)
                                            −{{ number_format($exOut, 2, ',', ' ') }}
                                        @endif
                                        @if ($exIn > 0.009)
                                            +{{ number_format($exIn, 2, ',', ' ') }}
                                        @endif
                                    </div>
                                @endif
                                @if ($negativeFloat)
                                    <div class="text-[11px] text-red-700 dark:text-red-300">ujemne — usuń wymianę lub wypłać PLN</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <div class="font-semibold text-indigo-800 dark:text-indigo-200">
                                    {{ number_format($spent, 2, ',', ' ') }}
                                </div>
                                <div class="text-[11px] text-gray-500">u dostawców</div>
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @if ($toReturn > 0.009)
                                    <div class="font-semibold text-teal-700 dark:text-teal-300">
                                        do zwrotu {{ number_format($toReturn, 2, ',', ' ') }}
                                    </div>
                                @elseif ($cashShortfall > 0.009)
                                    <div class="font-semibold text-amber-800 dark:text-amber-200">
                                        niedobór gotówki {{ number_format($cashShortfall, 2, ',', ' ') }}
                                    </div>
                                    @if ($negativeFloat)
                                        <div class="text-[11px] text-gray-500">po wymianie bez wypłaty z biura</div>
                                    @endif
                                @else
                                    <div class="text-gray-500">rozliczone</div>
                                @endif

                                @if ($editable)
                                    <div class="mt-1">
                                        <label class="mb-0.5 block text-[10px] text-gray-500">Zwrócono do biura</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.live.debounce.500ms="cashReturned.{{ $row->currency_id }}"
                                            class="{{ $compact ? 'pilot-field' : 'w-28 rounded-md border border-gray-300 px-2 py-1 text-right text-xs dark:border-gray-600 dark:bg-gray-950' }}"
                                            placeholder="0,00"
                                        />
                                    </div>
                                @elseif ($returnedInput > 0.009)
                                    <div class="mt-0.5 text-[11px] text-gray-500">
                                        zwrócono {{ number_format($returnedInput, 2, ',', ' ') }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($editable)
            @if ($compact)
                <button type="button" wire:click="saveCashReporting" class="pilot-touch-btn w-full border border-gray-300 bg-white text-gray-900">
                    Zapisz zwrot
                </button>
            @else
                <x-filament::button wire:click="saveCashReporting" size="sm" color="gray">
                    Zapisz zwrot
                </x-filament::button>
            @endif
        @endif
    @endif
</div>
