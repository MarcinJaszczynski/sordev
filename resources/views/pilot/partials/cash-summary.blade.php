@php

    $editable = $editable ?? true;

    $compact = $compact ?? false;

@endphp



<div class="space-y-4">

  @if($this->cashReconciliation->isEmpty())

    <p class="text-sm text-gray-500">

      Brak pozycji gotówkowych — biuro musi wpisać kwotę wydaną pilotowi w rozliczeniu.

    </p>

  @else

    <div class="divide-y rounded-lg border overflow-hidden">

      @foreach($this->cashReconciliation as $row)

        @php

          $returnedInput = $editable && filled($this->cashReturned[$row->currency_id] ?? null)

              ? (float) str_replace(',', '.', (string) $this->cashReturned[$row->currency_id])

              : $row->returned;

          $remaining = round($row->office_provided - $row->actual_spent - $returnedInput, 2);

          $toReturn = max(0, $remaining);

          $toPayPilot = max(0, -$remaining);

        @endphp

        <div class="px-3 py-3 {{ $compact ? 'text-sm' : '' }}">

          <div class="mb-2 font-medium text-gray-900">{{ $row->currency_name }}</div>

          <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">

            <dt class="text-gray-600">Wydano pilotowi (gotówka z biura)</dt>

            <dd class="text-right font-medium">{{ number_format($row->office_provided, 2, ',', ' ') }}</dd>

            <dt class="text-gray-600">Plan wydatków pilota</dt>

            <dd class="text-right text-gray-700">{{ number_format($row->planned_expenses, 2, ',', ' ') }}</dd>

            <dt class="text-gray-600">Wydał faktycznie (suma pozycji)</dt>

            <dd class="text-right font-medium">{{ number_format($row->actual_spent, 2, ',', ' ') }}</dd>

          </dl>



          @if($editable)

            <div class="mt-3">

              <label class="mb-1 block text-xs text-gray-600">Zwróciłem do biura</label>

              <input

                type="number"

                step="0.01"

                min="0"

                wire:model.live.debounce.500ms="cashReturned.{{ $row->currency_id }}"

                class="{{ $compact ? 'pilot-field' : 'fi-input w-full rounded-lg border px-3 py-2 text-sm' }}"

                placeholder="0,00"

              />

            </div>

          @elseif($returnedInput > 0)

            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">

              <dt class="text-gray-600">Zwrócone do biura</dt>

              <dd class="text-right">{{ number_format($returnedInput, 2, ',', ' ') }}</dd>

            </dl>

          @endif



          <div class="mt-3 rounded-lg px-3 py-2 text-sm {{ $toPayPilot > 0 ? 'bg-amber-50 text-amber-900' : ($toReturn > 0 ? 'bg-teal-50 text-teal-900' : 'bg-gray-50 text-gray-700') }}">

            @if($toReturn > 0)

              Do zwrotu do biura:

              <strong>{{ number_format($toReturn, 2, ',', ' ') }}</strong>

              <span class="block text-xs opacity-80 mt-0.5">

                = wydano {{ number_format($row->office_provided, 2, ',', ' ') }}

                − wydatki {{ number_format($row->actual_spent, 2, ',', ' ') }}

                @if($returnedInput > 0)

                  − zwrócono {{ number_format($returnedInput, 2, ',', ' ') }}

                @endif

              </span>

            @elseif($toPayPilot > 0)

              Do dopłaty pilotowi:

              <strong>{{ number_format($toPayPilot, 2, ',', ' ') }}</strong>

            @else

              Rozliczone — saldo zerowe.

            @endif

          </div>

        </div>

      @endforeach

    </div>



    @if($editable)

      @if($compact)

        <button type="button" wire:click="saveCashReporting" class="pilot-touch-btn w-full border border-gray-300 bg-white text-gray-900">

          Zapisz kwotę zwrotu

        </button>

      @else

        <x-filament::button wire:click="saveCashReporting" size="sm">

          Zapisz kwotę zwrotu

        </x-filament::button>

      @endif

    @endif

  @endif

</div>

