@php
    use App\Models\EventSettlementCost;
    $editable = $editable ?? true;
    $compact = $compact ?? false;
@endphp

<div class="space-y-4">
  @if($editable)
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" wire:click="refreshTripExpenses" class="{{ $compact ? 'pilot-touch-btn border border-gray-300 bg-white text-gray-900 text-sm' : 'text-sm text-primary-600 hover:underline' }}">
        Odśwież z wycieczki
      </button>
      <span class="text-xs text-gray-500">Pobiera z wycieczki tylko pozycje z płatnikiem „pilot”.</span>
    </div>
  @endif

  <div class="divide-y rounded-lg border overflow-hidden">
    @forelse($this->expenseLines as $cost)
      @php
        $currency = $cost->actualCurrency ?? $cost->plannedCurrency;
        $planned = (float) ($cost->planned_amount ?? 0);
        $actual = $cost->actual_amount !== null ? (float) $cost->actual_amount : null;
        $sourceLabel = EventSettlementCost::$sourceTypeLabels[$cost->source_type] ?? $cost->source_type;
        $paidByLabel = EventSettlementCost::$paidByOptions[$cost->paid_by] ?? $cost->paid_by;
        $docs = $cost->linkedDocuments();
        $isEditing = (int) $this->editingCostId === (int) $cost->id;
      @endphp

      <div class="px-3 py-3 {{ $compact ? 'text-sm' : '' }} {{ $isEditing ? 'bg-gray-50' : '' }}" wire:key="pilot-cost-{{ $cost->id }}">
        @if($isEditing)
          <div class="space-y-3">
            <div class="font-medium text-gray-900">{{ $cost->name }}</div>
            @if($planned > 0)
              <p class="text-xs text-gray-600">Plan: {{ number_format($planned, 2, ',', ' ') }} {{ $currency?->symbol ?? 'PLN' }}</p>
            @endif

            <div class="grid gap-3 {{ $compact ? '' : 'md:grid-cols-2' }}">
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Kwota faktyczna *</label>
                <input
                  type="text"
                  inputmode="decimal"
                  autocomplete="off"
                  wire:model.live.debounce.500ms="editCostActualAmount"
                  class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}"
                />
                @error('editCostActualAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Waluta</label>
                <select wire:model.live.debounce.500ms="editCostCurrencyId" class="{{ $compact ? 'pilot-field' : 'fi-select-input w-full rounded-lg border px-3 py-2 text-sm' }}">
                  @foreach($this->getCurrencyOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                  @endforeach
                </select>
                @error('editCostCurrencyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Forma płatności</label>
                <select wire:model.live.debounce.500ms="editCostPaymentMethod" class="{{ $compact ? 'pilot-field' : 'fi-select-input w-full rounded-lg border px-3 py-2 text-sm' }}">
                  @foreach(EventSettlementCost::$paymentMethods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
                @error('editCostPaymentMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Numer faktury</label>
                <input type="text" wire:model.live.debounce.500ms="editCostInvoiceNumber" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" />
                @error('editCostInvoiceNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Numer paragonu</label>
                <input type="text" wire:model.live.debounce.500ms="editCostReceiptNumber" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" />
                @error('editCostReceiptNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
              <div class="{{ $compact ? '' : 'md:col-span-2' }}">
                <label class="mb-1 block text-xs font-medium text-gray-600">Uwagi pilota</label>
                <input type="text" wire:model.live.debounce.500ms="editCostNotes" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" />
                @error('editCostNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              @if($compact)
                <button
                  type="button"
                  wire:click="saveCost"
                  wire:loading.attr="disabled"
                  wire:target="saveCost"
                  class="pilot-touch-btn bg-gray-800 text-white text-sm disabled:opacity-60"
                >
                  <span wire:loading.remove wire:target="saveCost">Zapisz kwotę</span>
                  <span wire:loading wire:target="saveCost">Zapisywanie…</span>
                </button>
              @else
                <x-filament::button type="button" wire:click="saveCost" size="sm" wire:target="saveCost">
                  Zapisz kwotę
                </x-filament::button>
              @endif
              <button type="button" wire:click="cancelEditCost" class="text-sm text-gray-600">Anuluj</button>
            </div>
          </div>
        @else
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
              <div class="font-medium text-gray-900">{{ $cost->name }}</div>
              <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs text-gray-600">
                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ $sourceLabel }}</span>
                <span class="rounded bg-amber-100 text-amber-900 px-1.5 py-0.5">{{ $paidByLabel }}</span>
                @if($planned > 0)
                  <span>plan: {{ number_format($planned, 2, ',', ' ') }} {{ $currency?->symbol ?? 'PLN' }}</span>
                @endif
              </div>
              <div class="mt-1 text-sm text-gray-800">
                @if($actual !== null)
                  Faktycznie:
                  <strong>{{ number_format($actual, 2, ',', ' ') }} {{ $currency?->symbol ?? 'PLN' }}</strong>
                @else
                  <span class="text-amber-700">Brak kwoty faktycznej</span>
                  @if($planned > 0)
                    <span class="text-gray-500">(plan: {{ number_format($planned, 2, ',', ' ') }})</span>
                  @endif
                @endif
                @if($cost->notes)
                  <span class="text-gray-600">· {{ $cost->notes }}</span>
                @endif
                @if($cost->invoice_number)
                  <span class="text-gray-600">· fv: {{ $cost->invoice_number }}</span>
                @endif
                @if($cost->receipt_number)
                  <span class="text-gray-600">· par: {{ $cost->receipt_number }}</span>
                @endif
                @if(! $cost->invoice_number && $cost->document_number)
                  <span class="text-gray-600">· dok: {{ $cost->document_number }}</span>
                @endif
              </div>
              @if($docs->isNotEmpty())
                <div class="mt-1 text-xs text-gray-600">
                  Dokumenty: {{ $docs->map(fn ($d) => $d->vendor_name ?: ('#'.$d->id))->join(', ') }}
                </div>
              @endif
            </div>
            @if($editable)
              <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button" wire:click="startEditCost({{ $cost->id }})" class="text-sm text-primary-600 hover:underline">Edytuj kwotę</button>
                @if($cost->source_type === 'manual')
                  <button type="button" wire:click="deleteExpense({{ $cost->id }})" class="text-sm text-danger-600 hover:underline">Usuń</button>
                @endif
              </div>
            @endif
          </div>

          @if($editable)
            <div class="mt-3 border-t border-dashed pt-3">
              <label class="mb-2 block text-xs font-medium text-gray-600">Dołącz skan do tego wydatku</label>
              @include('pilot.partials.photo-upload', [
                  'wireModel' => 'costDocumentFiles.'.$cost->id,
                  'compact' => $compact,
              ])
            </div>
          @endif
        @endif
      </div>
    @empty
      <div class="px-4 py-6 text-center text-sm text-gray-500">
        Brak wydatków pilota. @if($editable) Odśwież z wycieczki (jeśli biuro oznaczyło pozycje) lub dodaj wydatek nieprzewidziany. @endif
      </div>
    @endforelse
  </div>
</div>
