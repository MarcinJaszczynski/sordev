<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Środowiska — adresy, porównanie, przeliczanie --}}
        <x-filament::section icon="heroicon-o-globe-alt">
            <x-slot name="heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="font-bold text-base">Środowiska (adresy URL)</span>
                    <x-filament::button color="gray" size="sm" wire:click="resetEnvironmentUrls">
                        Przywróć z .env
                    </x-filament::button>
                </div>
            </x-slot>
            <x-slot name="description">
                Ustaw adresy aplikacji, na których chcesz <strong>porównywać</strong> lub <strong>przeliczać</strong> ceny.
                Zdalne środowiska wymagają wspólnego <code>PRICE_COMPARE_TOKEN</code> i wdrożonych endpointów
                <code>/internal/event-template-prices</code>.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 pr-3 text-left">Środowisko</th>
                            <th class="py-2 pr-3 text-left">Adres (base URL)</th>
                            <th class="py-2 px-2 text-center">Porównuj</th>
                            <th class="py-2 px-2 text-center">Przelicz</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($environmentSettings as $key => $env)
                            <tr wire:key="env-{{ $key }}">
                                <td class="py-3 pr-3 align-middle font-medium whitespace-nowrap">
                                    {{ $env['label'] ?? $key }}
                                    @if($env['is_local'] ?? false)
                                        <span class="ml-1 text-xs text-gray-400">(ta baza)</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3 align-middle">
                                    <input type="url"
                                        wire:model.blur="environmentSettings.{{ $key }}.url"
                                        placeholder="https://..."
                                        class="w-full min-w-[240px] px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white"/>
                                </td>
                                <td class="py-3 px-2 text-center align-middle">
                                    @if($env['is_local'] ?? false)
                                        <span class="text-xs text-gray-400" title="Local = zapisane + kalkulacja poniżej">local</span>
                                    @else
                                        <input type="checkbox" wire:model="environmentSettings.{{ $key }}.compare" class="rounded border-gray-300"/>
                                    @endif
                                </td>
                                <td class="py-3 px-2 text-center align-middle">
                                    <input type="checkbox" wire:model="environmentSettings.{{ $key }}.recalc" class="rounded border-gray-300"/>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Przelicz ceny — zawsze widoczne --}}
        <x-filament::section icon="heroicon-o-arrow-path" icon-color="warning">
            <x-slot name="heading">
                <span class="font-bold text-base">Przelicz ceny</span>
            </x-slot>
            <x-slot name="description">
                Aktualizuje zapisane ceny wg kalkulatora na zaznaczonych środowiskach (kolumna „Przelicz” powyżej).
                Bez wyników porównania — wybierz <strong>szablon</strong> w filtrach (opcjonalnie miejsce wyjazdu).
                Z wynikami porównania — przelicza pary z różnicami (lub wszystkie, jeśli odznaczysz poniżej).
            </x-slot>

            <div class="flex flex-wrap items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="recalcOnlyDiffs" class="rounded border-gray-300"/>
                    Tylko pary z różnicami (gdy jest tabela wyników)
                </label>

                <x-filament::button
                    color="warning"
                    icon="heroicon-o-arrow-path"
                    wire:click="bulkRecalculate"
                    wire:confirm="Przeliczyć ceny na zaznaczonych środowiskach?"
                    wire:loading.attr="disabled"
                    wire:target="bulkRecalculate"
                >
                    <span wire:loading.remove wire:target="bulkRecalculate">Przelicz na zaznaczonych środowiskach</span>
                    <span wire:loading wire:target="bulkRecalculate">Przeliczam…</span>
                </x-filament::button>

                @if(! config('price-comparison.token'))
                    <span class="text-xs text-amber-600 dark:text-amber-400">
                        Zdalne przeliczanie wymaga <code>PRICE_COMPARE_TOKEN</code> w .env.
                    </span>
                @endif
            </div>

            @if($recalcResults !== [])
                <div class="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Środowisko</th>
                                <th class="px-3 py-2 text-left">URL</th>
                                <th class="px-3 py-2 text-right">Przeliczono</th>
                                <th class="px-3 py-2 text-right">Par</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($recalcResults as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row['label'] }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 truncate max-w-xs">{{ $row['url'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $row['recalculated'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $row['pairs'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Filtry porównania --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="font-bold text-base">Porównanie cen</span>
            </x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Szablon imprezy</label>
                    <select wire:model="selectedTemplateId"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">— wybierz szablon —</option>
                        @foreach($this->getTemplateOptions() as $id => $name)
                            <option value="{{ $id }}">#{{ $id }} — {{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-amber-600">Wymagane dla kalkulacji live. Porównanie bez filtra zwróci błąd (~200 tys. cen w bazie).</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Miejsce wyjazdu</label>
                    <select wire:model="selectedStartPlaceId"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">— opcjonalnie —</option>
                        @foreach($this->getStartPlaceOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Próg różnicy (PLN)</label>
                    <input type="number" step="0.01" min="0" wire:model="threshold"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:border-gray-600 dark:bg-gray-800 dark:text-white"/>
                </div>

                <div class="flex flex-col justify-end gap-2">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="onlyDiffs" class="rounded border-gray-300"/>
                        Tylko różnice w tabeli
                    </label>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="includeStored" class="rounded border-gray-300"/>
                    Zapisane ceny (przed kalkulacją)
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="includeCalculated" class="rounded border-gray-300"/>
                    Kalkulacja live (po przeliczeniu)
                </label>
            </div>

            <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                @if($this->countActiveSources() >= 2)
                    <span class="font-medium">Źródła porównania ({{ $this->countActiveSources() }}):</span>
                    {{ implode(' · ', $this->getActiveSourceLabels()) }}
                @elseif($this->countActiveSources() === 1)
                    <span class="text-amber-600">Zaznaczone 1 źródło — zaznacz jeszcze jedno (np. Zapisane + Kalkulacja), aby pobrać CSV z różnicami.</span>
                @else
                    <span class="text-amber-600">Zaznacz co najmniej 2 źródła: checkboxy powyżej lub kolumna „Porównuj” w środowiskach.</span>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-filament::button
                    wire:click="runComparison"
                    wire:loading.attr="disabled"
                    wire:target="runComparison"
                    icon="heroicon-o-play"
                >
                    <span wire:loading.remove wire:target="runComparison">Uruchom porównanie</span>
                    <span wire:loading wire:target="runComparison">Porównuję…</span>
                </x-filament::button>

                @if($this->canExportComparison())
                    <x-filament::button
                        color="primary"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="downloadComparisonCsv"
                        wire:loading.attr="disabled"
                        wire:target="downloadComparisonCsv"
                    >
                        <span wire:loading.remove wire:target="downloadComparisonCsv">Eksport CSV — porównanie {{ $this->countActiveSources() }} źródeł</span>
                        <span wire:loading wire:target="downloadComparisonCsv">Generuję CSV…</span>
                    </x-filament::button>
                @else
                    <span class="text-xs text-amber-600">{{ $this->exportComparisonBlockedReason() }}</span>
                @endif

                @if($comparisonRows !== [])
                    <x-filament::button
                        color="gray"
                        icon="heroicon-o-table-cells"
                        wire:click="exportCsv"
                        wire:loading.attr="disabled"
                        wire:target="exportCsv"
                    >
                        Eksport tabeli (wyniki poniżej)
                    </x-filament::button>
                @endif
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Pełna oferta — porównanie 3 środowisk (local · prod · dev)</div>
                <p class="text-xs text-gray-500 mb-3">
                    Zapisane ceny za osobę z każdego serwera w jednym CSV:
                    <code>cena_local_pln</code>, <code>cena_prod_pln</code>, <code>cena_dev_pln</code>
                    oraz <code>roznica_prod_pln</code>, <code>roznica_dev_pln</code> (Δ względem local).
                    Opcjonalnie zawęź filtrami powyżej (szablon / miasto). Respektuje „Tylko różnice” i próg PLN.
                </p>

                @if($this->canExportEnvironmentComparison())
                    <x-filament::button
                        color="primary"
                        size="sm"
                        icon="heroicon-o-table-cells"
                        wire:click="downloadEnvironmentComparisonCsv"
                        wire:loading.attr="disabled"
                        wire:target="downloadEnvironmentComparisonCsv"
                    >
                        <span wire:loading.remove wire:target="downloadEnvironmentComparisonCsv">Eksport CSV — local + prod + dev (3/3)</span>
                        <span wire:loading wire:target="downloadEnvironmentComparisonCsv">Sprawdzam środowiska…</span>
                    </x-filament::button>
                    <p class="mt-2 text-xs text-gray-500">
                        Wymaga wdrożonego endpointu <code>/internal/event-template-prices</code> na prod i dev oraz tego samego <code>PRICE_COMPARE_TOKEN</code>.
                    </p>
                @else
                    <p class="text-xs text-amber-600">{{ $this->environmentComparisonBlockedReason() }}</p>
                @endif

                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Surowy cennik (1 kolumna, bez różnic)</div>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-o-document"
                        tag="a"
                        href="{{ $this->getFullCatalogExportUrl() }}"
                        target="_blank"
                    >
                        Pobierz zapisane ceny local (1 źródło)
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        @if($errors !== [])
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Ostrzeżenia</x-slot>
                <ul class="list-disc pl-5 text-sm space-y-1">
                    @foreach($errors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if($summary !== [])
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-xl bg-slate-100 dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase text-gray-500">Kombinacji</div>
                    <div class="text-2xl font-bold">{{ $summary['total_keys'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl bg-slate-100 dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase text-gray-500">Wierszy w tabeli</div>
                    <div class="text-2xl font-bold">{{ $summary['shown_rows'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl bg-amber-50 dark:bg-amber-900/30 p-4">
                    <div class="text-xs uppercase text-amber-700 dark:text-amber-300">Różnice &gt; progu</div>
                    <div class="text-2xl font-bold text-amber-800 dark:text-amber-200">{{ $summary['diff_rows'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 p-4">
                    <div class="text-xs uppercase text-rose-700 dark:text-rose-300">Brakujące w źródle</div>
                    <div class="text-2xl font-bold text-rose-800 dark:text-rose-200">{{ $summary['missing_rows'] ?? 0 }}</div>
                </div>
            </div>
        @endif

        @if($comparisonRows !== [])
            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-bold text-base">Wyniki porównania (PLN / os.)</span>
                </x-slot>

                <div class="overflow-x-auto -mx-2">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-3 py-2">Szablon</th>
                                <th class="px-3 py-2">Miejsce wyjazdu</th>
                                <th class="px-3 py-2 text-right">Osób</th>
                                @if($includeStored)
                                    <th class="px-3 py-2 text-right">Zapisane</th>
                                @endif
                                @if($includeCalculated)
                                    <th class="px-3 py-2 text-right">Kalkulacja<br><span class="font-normal normal-case text-gray-400">Δ vs zapisane</span></th>
                                @endif
                                @foreach($environmentSettings as $key => $env)
                                    @if(($env['compare'] ?? false) && !($env['is_local'] ?? false))
                                        <th class="px-3 py-2 text-right">{{ $env['label'] ?? $key }}<br><span class="font-normal normal-case text-gray-400">Δ vs zapisane</span></th>
                                    @endif
                                @endforeach
                                <th class="px-3 py-2 text-right">Max Δ</th>
                                <th class="px-3 py-2">Linki</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($comparisonRows as $row)
                                @php
                                    $isHot = ($row['max_diff'] ?? 0) > $threshold || ($row['has_missing'] ?? false);
                                @endphp
                                <tr class="{{ $isHot ? 'bg-rose-50/60 dark:bg-rose-950/20' : '' }}">
                                    <td class="px-3 py-2">
                                        <div class="font-medium">#{{ $row['template_id'] }}</div>
                                        <div class="text-xs text-gray-500 truncate max-w-[220px]" title="{{ $row['template_name'] }}">{{ $row['template_name'] }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ $row['start_place_name'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $row['qty'] }}</td>
                                    @if($includeStored)
                                        <td class="px-3 py-2 text-right font-mono">{{ $this->formatPrice($row['stored_price'] ?? null) }}</td>
                                    @endif
                                    @if($includeCalculated)
                                        <td class="px-3 py-2 text-right font-mono">
                                            <div>{{ $this->formatPrice($row['calculated_price'] ?? null) }}</div>
                                            @if(($row['delta_calculated'] ?? null) !== null)
                                                @php $d = (float) $row['delta_calculated']; @endphp
                                                <div class="text-xs {{ abs($d) > $threshold ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-400' }}">
                                                    {{ $this->formatDelta($d) }}
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    @foreach($environmentSettings as $key => $env)
                                        @if(($env['compare'] ?? false) && !($env['is_local'] ?? false))
                                            <td class="px-3 py-2 text-right font-mono">
                                                <div>{{ $this->formatPrice($row[$key.'_price'] ?? null) }}</div>
                                                @if(($row['delta_'.$key] ?? null) !== null)
                                                    @php $d = (float) $row['delta_'.$key]; @endphp
                                                    <div class="text-xs {{ abs($d) > $threshold ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-400' }}">
                                                        {{ $this->formatDelta($d) }}
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="px-3 py-2 text-right font-mono font-semibold {{ $isHot ? 'text-rose-600 dark:text-rose-400' : '' }}">
                                        {{ number_format((float) ($row['max_diff'] ?? 0), 0, ',', ' ') }} zł
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <a href="{{ $this->templateEditUrl((int) $row['template_id']) }}"
                                           class="text-primary-600 hover:underline text-xs" target="_blank" rel="noopener">Kalkulacja</a>
                                        @if($www = $this->templateWwwUrl((int) $row['template_id'], $row['start_place_id'] ?? null))
                                            · <a href="{{ $www }}" class="text-primary-600 hover:underline text-xs" target="_blank" rel="noopener">WWW</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
