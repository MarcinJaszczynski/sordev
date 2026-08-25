@php
    use App\Models\EventSettlementCost;
    use Illuminate\Support\Facades\Storage;

    $editable = $editable ?? true;
    $compact = $compact ?? false;
    $isPilotContext = ($this->context ?? 'admin') === 'pilot';
@endphp

<div class="space-y-3">
  @if($editable)
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" wire:click="refreshTripExpenses" class="{{ $compact ? 'pilot-touch-btn border border-gray-300 bg-white text-gray-900 text-sm' : 'text-sm text-primary-600 hover:underline' }}">
        Odśwież z wycieczki
      </button>
      <span class="text-xs text-gray-500">Tylko pozycje z płatnikiem „pilot”.</span>
    </div>
  @endif

  <div class="overflow-x-auto overflow-y-visible rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
      <thead class="bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/80">
        <tr>
          <th class="px-3 py-2">Punkt / set</th>
          <th class="px-3 py-2 whitespace-nowrap">Zapłacono</th>
          <th class="px-3 py-2">Plik / zdjęcie</th>
          <th class="px-3 py-2">Komu</th>
          <th class="px-3 py-2">Uwagi</th>
          @if($editable)
            <th class="px-3 py-2"></th>
          @endif
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
        @forelse($this->expenseLines as $cost)
          @php
            $currency = $cost->actualCurrency ?? $cost->plannedCurrency;
            $symbol = $currency?->symbol ?? $currency?->code ?? 'PLN';
            $planned = (float) ($cost->planned_amount ?? 0);
            $officePaid = (float) ($cost->ledger_office_paid ?? 0);
            $pilotDue = (float) ($cost->ledger_pilot_due ?? max(0, $planned - $officePaid));
            $actual = $cost->ledger_paid_amount !== null
                ? (float) $cost->ledger_paid_amount
                : ($cost->actual_amount !== null ? (float) $cost->actual_amount : null);
            $docs = $cost->linkedDocuments();
            $firstFile = null;
            foreach ($docs as $document) {
                foreach (collect($document->files ?? [])->filter() as $path) {
                    $firstFile = ['path' => $path, 'doc' => $document];
                    break 2;
                }
            }
            $payee = $cost->contractor?->displayLabel() ?? '—';
            $isEditing = (int) $this->editingCostId === (int) $cost->id;
            $isUnplanned = $cost->source_type === 'manual';
          @endphp

          @if($isEditing)
            <tr class="bg-sky-50/60 dark:bg-sky-950/20" wire:key="pilot-cost-edit-{{ $cost->id }}">
              <td colspan="{{ $editable ? 6 : 5 }}" class="px-3 py-3">
                <div class="space-y-3">
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cost->name }}</div>
                    @if($isUnplanned)
                      <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                        Nieplanowany
                      </span>
                    @endif
                  </div>

                  @if($officePaid > 0.009)
                    <div class="rounded-lg border border-teal-300 bg-teal-50 px-3 py-2 text-sm text-teal-950 dark:border-teal-700 dark:bg-teal-950/40 dark:text-teal-100">
                      <p class="font-semibold">
                        Biuro wpłaciło zaliczkę: {{ number_format($officePaid, 2, ',', ' ') }} {{ $symbol }}
                      </p>
                      @if($planned > 0.009)
                        <p class="mt-0.5 text-xs text-teal-800 dark:text-teal-200">
                          Plan {{ number_format($planned, 2, ',', ' ') }} {{ $symbol }}
                          → dopłata pilota: <strong>{{ number_format($pilotDue, 2, ',', ' ') }} {{ $symbol }}</strong>
                        </p>
                      @endif
                      <p class="mt-1 text-[11px] font-medium text-teal-900/80 dark:text-teal-100/80">
                        Nie płacisz pełnej kwoty planu — tylko dopłatę (albo 0, jeśli już pokryte).
                      </p>
                    </div>
                  @elseif($planned > 0)
                    <p class="text-xs text-gray-600">
                      Planowane: {{ number_format($planned, 2, ',', ' ') }} {{ $symbol }}
                      @if($pilotDue > 0.009)
                        · do zapłaty: {{ number_format($pilotDue, 2, ',', ' ') }} {{ $symbol }}
                      @endif
                    </p>
                  @endif

                  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-600">
                        Kwota faktyczna (gotówka pilota) *
                        @if($officePaid > 0.009 && $pilotDue > 0.009)
                          <span class="font-normal text-teal-700">— domyślnie dopłata</span>
                        @endif
                      </label>
                      <input type="text" inputmode="decimal" autocomplete="off" wire:model.live.debounce.500ms="editCostActualAmount" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" />
                      @error('editCostActualAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-600">Waluta</label>
                      <select wire:model.live.debounce.500ms="editCostCurrencyId" class="{{ $compact ? 'pilot-field' : 'fi-select-input w-full rounded-lg border px-3 py-2 text-sm' }}">
                        @foreach($this->getCurrencyOptions() as $id => $name)
                          <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-600">Uwagi</label>
                      <input type="text" wire:model.live.debounce.500ms="editCostNotes" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" />
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-medium text-gray-600">Nr faktury / paragonu</label>
                      <input type="text" wire:model.live.debounce.500ms="editCostInvoiceNumber" class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" placeholder="Faktura" />
                      <input type="text" wire:model.live.debounce.500ms="editCostReceiptNumber" class="mt-1 {{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}" placeholder="Paragon" />
                    </div>
                  </div>

                  <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button type="button" wire:click="saveCost" wire:loading.attr="disabled" wire:target="saveCost" class="{{ $compact ? 'pilot-touch-btn bg-primary-600 text-white text-sm' : 'inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700' }}">
                      Zapisz
                    </button>
                    <button type="button" wire:click="cancelEditCost" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">Anuluj</button>
                  </div>
                </div>
              </td>
            </tr>
          @else
            <tr @class([
              'bg-amber-50/70 dark:bg-amber-950/20' => $isUnplanned,
              'bg-teal-50/50 dark:bg-teal-950/20' => ! $isUnplanned && $officePaid > 0.009,
            ]) wire:key="pilot-cost-{{ $cost->id }}">
              <td class="px-3 py-2 align-top">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="font-medium text-gray-900 dark:text-gray-100">{{ $cost->name }}</div>
                  @if($isUnplanned)
                    <span class="inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-950 dark:bg-amber-800 dark:text-amber-50">
                      Nieplanowany
                    </span>
                  @endif
                  @if($officePaid > 0.009)
                    <span class="inline-flex items-center rounded-full bg-teal-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-950 dark:bg-teal-800 dark:text-teal-50">
                      Zaliczka biura
                    </span>
                  @endif
                </div>
                @if($officePaid > 0.009)
                  <div class="mt-1 rounded-md border border-teal-200 bg-teal-50/80 px-2 py-1.5 text-[11px] leading-snug text-teal-950 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-100">
                    <div>
                      <span class="font-semibold">Biuro wpłaciło:</span>
                      {{ number_format($officePaid, 2, ',', ' ') }} {{ $symbol }}
                    </div>
                    @if($planned > 0.009)
                      <div class="text-teal-800 dark:text-teal-200">
                        plan {{ number_format($planned, 2, ',', ' ') }} {{ $symbol }}
                        · <span class="font-semibold">{{ $isPilotContext ? 'pilot dopłaca' : 'dopłata' }} {{ number_format($pilotDue, 2, ',', ' ') }} {{ $symbol }}</span>
                      </div>
                    @endif
                  </div>
                @elseif($planned > 0.009)
                  <div class="mt-0.5 text-[11px] text-gray-500">
                    plan {{ number_format($planned, 2, ',', ' ') }} {{ $symbol }}
                    @if($pilotDue > 0.009 && ($actual === null || abs($actual - $pilotDue) > 0.009))
                      · do zapłaty {{ number_format($pilotDue, 2, ',', ' ') }}
                    @endif
                  </div>
                @elseif($isUnplanned)
                  <div class="mt-0.5 text-[11px] font-medium text-amber-800 dark:text-amber-200">
                    dodany przez pilota — poza planem
                  </div>
                @endif
              </td>
              <td class="px-3 py-2 align-top whitespace-nowrap">
                @if($actual !== null)
                  <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($actual, 2, ',', ' ') }} {{ $symbol }}</span>
                @elseif($pilotDue > 0.009)
                  <span class="text-amber-700">—</span>
                @else
                  <span class="text-teal-700 text-xs">pokryte biurem</span>
                @endif
              </td>
              <td class="px-3 py-2 align-top">
                @if($firstFile)
                  @php
                    $name = basename((string) $firstFile['path']);
                    $url = Storage::disk('public')->url($firstFile['path']);
                    $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $name);
                  @endphp
                  <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-[#0663fc] hover:underline">
                    {{ $isImage ? '📷 Zdjęcie' : '📄 Plik' }}
                  </a>
                  @if($docs->flatMap(fn ($d) => collect($d->files ?? []))->filter()->count() > 1)
                    <span class="text-[11px] text-gray-500">+{{ $docs->flatMap(fn ($d) => collect($d->files ?? []))->filter()->count() - 1 }}</span>
                  @endif
                @elseif($editable)
                  <div class="max-w-[11rem]">
                    @include('pilot.partials.photo-upload', [
                        'wireModel' => 'costDocumentFiles.'.$cost->id,
                        'compact' => true,
                    ])
                  </div>
                @else
                  <span class="text-xs text-gray-400">—</span>
                @endif
              </td>
              <td class="px-3 py-2 align-top text-gray-700 dark:text-gray-300">{{ $payee }}</td>
              <td class="px-3 py-2 align-top text-gray-600 dark:text-gray-400">
                {{ $cost->notes ?: '—' }}
                @if($cost->invoice_number)
                  <span class="block text-[11px] text-gray-500">fv: {{ $cost->invoice_number }}</span>
                @endif
                @if($cost->receipt_number)
                  <span class="block text-[11px] text-gray-500">par: {{ $cost->receipt_number }}</span>
                @endif
              </td>
              @if($editable)
                <td class="px-3 py-2 align-top whitespace-nowrap text-right">
                  <button type="button" wire:click="startEditCost({{ $cost->id }})" class="text-xs font-medium text-[#0663fc] hover:underline">Edytuj</button>
                  @if($cost->source_type === 'manual')
                    <button type="button" wire:click="deleteExpense({{ $cost->id }})" class="ml-2 text-xs text-red-600 hover:underline">Usuń</button>
                  @endif
                </td>
              @endif
            </tr>
          @endif
        @empty
          <tr>
            <td colspan="{{ $editable ? 6 : 5 }}" class="px-4 py-6 text-center text-sm text-gray-500">
              Brak wydatków pilota.
              @if($editable) Odśwież z wycieczki albo dodaj wydatek nieprzewidziany. @endif
            </td>
          </tr>
        @endforelse
      </tbody>
      @if ($this->expenseLines->isNotEmpty())
        <tfoot class="bg-gray-50 text-xs dark:bg-gray-800/80">
          @foreach ($this->expenseLedgerTotals as $total)
            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="expense-total-{{ $total['currency'] }}">
              <td class="px-3 py-2 font-semibold text-gray-800 dark:text-gray-100">
                Suma ({{ $total['currency'] }})
              </td>
              <td class="px-3 py-2 align-top whitespace-nowrap">
                <div class="font-semibold text-gray-900 dark:text-gray-100">
                  {{ number_format($total['pilot_paid'], 2, ',', ' ') }} {{ $total['currency'] }}
                </div>
                <div class="mt-0.5 space-y-0.5 text-[11px] font-normal text-gray-500">
                  <div>plan {{ number_format($total['planned'], 2, ',', ' ') }}</div>
                  @if ($total['office_paid'] > 0.009)
                    <div>zaliczka biura {{ number_format($total['office_paid'], 2, ',', ' ') }}</div>
                  @endif
                  @if ($total['pilot_due'] > 0.009)
                    <div>do pilota {{ number_format($total['pilot_due'], 2, ',', ' ') }}</div>
                  @endif
                </div>
              </td>
              <td class="px-3 py-2" colspan="{{ $editable ? 4 : 3 }}"></td>
            </tr>
          @endforeach
        </tfoot>
      @endif
    </table>
  </div>
</div>
