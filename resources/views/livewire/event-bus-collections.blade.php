<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Zaplanuj ile pilot ma zebrać, potem zapisz ile zebrał. Zebrana gotówka zasila saldo pilota (bez wypłaty z biura).
        Po wycieczce przekaż resztę do biura i potwierdź.
    </p>

    @if(filled($defaultsHint))
        <p class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
            {{ $defaultsHint }}
            @if(! $readOnly)
                <button type="button" wire:click="applyEventDefaults" class="ml-2 font-medium underline hover:no-underline">
                    Wstaw z imprezy
                </button>
            @endif
        </p>
    @endif

    @if($totalsByCurrency->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($totalsByCurrency as $total)
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ $total['currency']?->code ?: $total['currency']?->symbol ?: '—' }}
                    </span>
                    @if($total['planned'] > 0.009)
                        <span class="ml-2 text-sky-800 dark:text-sky-200">plan {{ $money($total['planned'], $total['currency']) }}</span>
                    @endif
                    @if($total['held'] > 0.009)
                        <span class="ml-2 text-teal-800 dark:text-teal-200">u pilota {{ $money($total['held'], $total['currency']) }}</span>
                    @endif
                    @if($total['handed'] > 0.009)
                        <span class="ml-2 text-gray-500">w biurze {{ $money($total['handed'], $total['currency']) }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if(! $readOnly)
        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Tytuł</label>
                <input type="text" wire:model.live.debounce.500ms="title" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
                @error('title') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Data i godzina (przy zebraniu)</label>
                <input type="datetime-local" wire:model.live.debounce.500ms="collectedAt" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
                @error('collectedAt') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Kwota za osobę</label>
                <input type="number" step="0.01" wire:model.live.debounce.500ms="unitAmount" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" placeholder="np. 50" />
                @error('unitAmount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Liczba osób</label>
                <input type="number" min="1" wire:model.live.debounce.500ms="participantCount" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" placeholder="np. 42" />
                @error('participantCount') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Waluta</label>
                <select wire:model.live.debounce.500ms="currencyId" class="fi-select-input w-full rounded-lg border px-3 py-2 text-sm">
                    @foreach($currencyOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <div class="w-full rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm dark:border-primary-800 dark:bg-primary-950/40">
                    <span class="text-gray-600 dark:text-gray-400">Suma:</span>
                    <span class="ml-1 font-semibold text-gray-900 dark:text-gray-100">
                        @if($computedTotal !== null)
                            {{ $money($computedTotal, $currency) }}
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Uwagi</label>
                <input type="text" wire:model.live.debounce.500ms="notes" class="fi-input w-full rounded-lg border px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-2">
                <x-filament::button wire:click="addPlan" size="sm" color="gray" icon="heroicon-o-clipboard-document-list">
                    Zapisz plan
                </x-filament::button>
                <x-filament::button wire:click="addCollection" size="sm" icon="heroicon-o-banknotes">
                    Zapisz jako zebrane
                </x-filament::button>
            </div>
        </div>
    @endif

    <div class="divide-y rounded-xl border border-gray-200 dark:border-gray-700">
        @forelse($collections as $collection)
            @php
                $planned = $collection->planned_amount !== null ? (float) $collection->planned_amount : null;
                $actual = (float) $collection->amount;
                $showPlanDiff = $planned !== null
                    && $collection->status !== \App\Models\EventBusCollection::STATUS_PLANNED
                    && abs($planned - $actual) > 0.009;
            @endphp
            <div class="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" wire:key="bus-collection-{{ $collection->id }}">
                <div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $collection->title }}
                        <span class="ml-2 text-gray-500">· {{ $money($actual, $collection->currency) }}</span>
                    </div>
                    <div class="text-gray-600 dark:text-gray-400">
                        {{ $collection->collected_at?->format('d.m.Y H:i') ?? ($collection->isPlanned() ? 'plan' : '—') }}
                        @if($collection->participant_count)
                            · {{ $collection->participant_count }} os.
                            @if($collection->amount_per_person)
                                ({{ $money((float) $collection->amount_per_person, $collection->currency) }}/os.)
                            @endif
                        @endif
                        · {{ \App\Models\EventBusCollection::$statuses[$collection->status] ?? $collection->status }}
                        @if($collection->recorder)
                            · {{ $collection->recorder->name }}
                        @endif
                    </div>
                    @if($showPlanDiff)
                        <div class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                            Plan: {{ $money($planned, $collection->currency) }}
                            · różnica {{ $money($actual - $planned, $collection->currency) }}
                        </div>
                    @elseif($planned !== null && $collection->isPlanned())
                        <div class="mt-1 text-xs text-sky-800 dark:text-sky-200">
                            Do zebrania: {{ $money($planned, $collection->currency) }}
                        </div>
                    @endif
                    @if(filled($collection->notes))
                        <div class="mt-1 text-gray-500">{{ $collection->notes }}</div>
                    @endif
                </div>
                @if(! $readOnly)
                    <div class="flex flex-wrap items-center gap-2">
                        @if($collection->status === \App\Models\EventBusCollection::STATUS_PLANNED)
                            <button type="button" wire:click="markCollected({{ $collection->id }})" class="text-xs font-medium text-teal-700 hover:underline dark:text-teal-300">
                                Oznacz jako zebrane
                            </button>
                        @elseif($collection->status === \App\Models\EventBusCollection::STATUS_COLLECTED)
                            <button type="button" wire:click="updateStatus({{ $collection->id }}, 'handed_to_office')" class="text-xs font-medium text-primary-600 hover:underline">
                                Przekazano do biura
                            </button>
                        @elseif($collection->status === \App\Models\EventBusCollection::STATUS_HANDED_TO_OFFICE)
                            <button type="button" wire:click="updateStatus({{ $collection->id }}, 'confirmed')" class="text-xs font-medium text-success-600 hover:underline">
                                Potwierdź
                            </button>
                        @endif
                        <button type="button" wire:click="deleteCollection({{ $collection->id }})" wire:confirm="Usunąć tę zbiórkę?" class="text-xs text-danger-600 hover:underline">
                            Usuń
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-gray-500">Brak planów i zbiórek w autokarze.</div>
        @endforelse
    </div>
</div>
