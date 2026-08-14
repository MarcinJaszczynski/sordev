@php
    $maxDay = $this->settlementCostDrawerMaxDay;
    $currencyOptions = $this->currencyOptions;
    $contractorOptions = $this->contractorOptions;
    $isManualCostForm = ($this->costFormMode ?? 'program') === 'manual';
@endphp

<div class="grid gap-2 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="text-xs text-gray-600">Nazwa</label>
        <input type="text" wire:model="costForm.name" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
        @error('costForm.name') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label class="text-xs text-gray-600">Kontrahent</label>
        <select wire:model="costForm.contractor_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
            <option value="">— bez kontrahenta —</option>
            @foreach ($contractorOptions as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('costForm.contractor_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-xs text-gray-600">Kwota</label>
        <input type="number" step="0.01" wire:model.live="costForm.amount" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
        @error('costForm.amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="text-xs text-gray-600">Waluta</label>
        <select wire:model.live="costForm.currency_id" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
            @foreach ($currencyOptions as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('costForm.currency_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
    @php
        $costCurrencyId = $this->costForm['currency_id'] ?? null;
        $costCurrency = $costCurrencyId ? \App\Models\Currency::query()->find($costCurrencyId) : null;
        $costIsForeign = \App\Support\CurrencyAmountDisplay::isForeignCurrency($costCurrencyId);
        $costConvert = (bool) ($this->costForm['convert_to_pln'] ?? true);
        $costAmount = (float) ($this->costForm['amount'] ?? 0);
        $costAmountPreview = $costAmount > 0
            ? \App\Support\CurrencyAmountDisplay::format($costAmount, $costCurrency, $costIsForeign && $costConvert)
            : null;
    @endphp
    @if ($costIsForeign)
        <div class="sm:col-span-2 space-y-1 pt-1">
            <div class="flex items-center gap-2">
                <input id="cost-convert-to-pln" type="checkbox" wire:model.live="costForm.convert_to_pln" class="rounded border-gray-300" />
                <label for="cost-convert-to-pln" class="text-xs text-gray-700 dark:text-gray-200">
                    Przeliczaj do sum PLN (oferta / rollupy)
                </label>
            </div>
            @if ($costAmountPreview)
                <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    Podgląd: <span class="font-medium tabular-nums">{{ $costAmountPreview }}</span>
                    @if ($costConvert)
                        <span class="text-gray-500">· wchodzi do sum PLN</span>
                    @else
                        <span class="text-gray-500">· poza sumą PLN</span>
                    @endif
                </p>
            @endif
        </div>
    @endif
    <div>
        <label class="text-xs text-gray-600">Płatnik</label>
        <select wire:model="costForm.paid_by" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
            @foreach (\App\Models\EventSettlementCost::$paidByOptions as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
    </div>
    @unless ($isManualCostForm)
    <div>
        <label class="text-xs text-gray-600">Dzień programu</label>
        <select wire:model="costForm.day" class="fi-select-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
            @for ($d = 1; $d <= $maxDay; $d++)
                <option value="{{ $d }}">Dzień {{ $d }}</option>
            @endfor
        </select>
    </div>
    @endunless
    <div>
        <label class="text-xs text-gray-600">Termin płatności</label>
        <input type="date" wire:model="costForm.due_date" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
    </div>
    <div class="sm:col-span-2">
        <label class="text-xs text-gray-600">Notatka</label>
        <textarea wire:model="costForm.notes" rows="2" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
    </div>
</div>
<div class="flex gap-2 pt-2">
    <x-filament::button size="sm" wire:click="saveCost">
        {{ $isManualCostForm ? 'Zapisz wydatek' : 'Zapisz koszt' }}
    </x-filament::button>
    <x-filament::button size="sm" color="gray" wire:click="$set('showCostForm', false)">Anuluj</x-filament::button>
</div>
<p class="pt-1 text-[11px] text-gray-500">
    @if ($isManualCostForm)
        Wydatek nie trafia do programu — tylko do rozliczenia finansowego imprezy.
    @else
        Przy walucie obcej bez przeliczenia kwota zostaje poza sumą PLN (w programie bez ≈ PLN).
    @endif
</p>
