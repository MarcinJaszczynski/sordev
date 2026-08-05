@php
    use App\Support\MoneyFormatter;
@endphp

<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    @if(! $contract || ! $presentation)
        <div class="sor-lw-card text-sm text-gray-600">
            Brak harmonogramu płatności dla Twojej umowy.
        </div>
    @else
        <div class="space-y-4">
            <section class="sor-lw-card">
                <h3 class="sor-lw-title">Podsumowanie</h3>
                <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Schemat płatności</dt>
                        <dd class="font-medium">{{ $presentation['payment_scheme_label'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Kwota umowy</dt>
                        <dd class="font-medium">
                            {{ MoneyFormatter::format((float) ($presentation['total_amount'] ?? 0), $contract->currency ?: 'PLN') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="sor-lw-card overflow-hidden !p-0">
                <header class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                    <h3 class="sor-lw-title">Transze</h3>
                </header>
                @if(empty($presentation['payment_schedules']))
                    <p class="px-4 py-3 text-sm text-gray-500">Brak zdefiniowanych transz.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Opis</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Termin</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Kwota</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Wpłacono</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Akcja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($presentation['payment_schedules'] as $schedule)
                                    <tr>
                                        <td class="px-4 py-2">{{ $schedule['label'] ?: '—' }}</td>
                                        <td class="px-4 py-2">{{ $schedule['due_date'] ?: '—' }}</td>
                                        <td class="px-4 py-2 text-right font-medium">
                                            {{ MoneyFormatter::format((float) ($schedule['amount'] ?? 0), $contract->currency ?: 'PLN') }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            {{ MoneyFormatter::format((float) ($schedule['paid_amount'] ?? 0), $contract->currency ?: 'PLN') }}
                                        </td>
                                        <td class="px-4 py-2">
                                            @if($schedule['is_paid'] ?? false)
                                                <span class="text-emerald-700">Opłacona</span>
                                            @elseif(filled($schedule['due_date']))
                                                <span class="text-blue-700">W terminie</span>
                                            @else
                                                <span class="text-gray-600">Oczekuje</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @if(!($schedule['is_paid'] ?? false) && !empty($schedule['id']) && $contract instanceof \App\Models\Contract)
                                                <x-filament::button
                                                    size="sm"
                                                    color="primary"
                                                    wire:click="payInstallment({{ (int) $schedule['id'] }})"
                                                >
                                                    Opłać ratę
                                                </x-filament::button>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    @endif
</x-filament-panels::page>
