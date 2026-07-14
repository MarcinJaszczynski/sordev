<div class="space-y-4">

    @if(session('status'))

        <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">

            {{ session('status') }}

        </div>

    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if($showTripHeader)
    <section class="pilot-card">

        <h2 class="mb-2 text-base font-semibold text-gray-900">{{ $event->name }}</h2>

        <p class="text-sm text-gray-600">

            {{ $event->start_date?->format('d.m.Y') }} – {{ $event->end_date?->format('d.m.Y') ?? '—' }}

            · plan: {{ $event->participant_count ?? '—' }} os.

        </p>

        <p class="mt-2 text-sm text-gray-600">

            Status rozliczenia:

            <strong>{{ \App\Models\EventSettlement::$statuses[$settlement->status] ?? $settlement->status }}</strong>

        </p>

    </section>
    @else
        <p class="text-sm text-gray-600">
            Status rozliczenia:
            <strong>{{ \App\Models\EventSettlement::$statuses[$settlement->status] ?? $settlement->status }}</strong>
        </p>
    @endif



    @if(!$this->editable)

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">

            Rozliczenie zamknięte — tryb tylko do odczytu.

        </div>

    @endif



    <section class="pilot-card">

        <h3 class="mb-3 font-semibold text-gray-900">Uwagi pilota</h3>

        <textarea wire:model.live.debounce.500ms="pilot_report_notes" rows="4" class="pilot-field" @disabled(!$this->editable)></textarea>

    </section>



    <section class="pilot-card">

        <h3 class="mb-3 font-semibold text-gray-900">Dane z wycieczki</h3>

        <div class="space-y-3">

            <div>

                <label class="mb-1 block text-sm font-medium text-gray-700">Liczba osób (faktyczna)</label>

                <input type="number" wire:model.live.debounce.500ms="reported_participant_count" class="pilot-field" min="0" @disabled(!$this->editable)>

            </div>

            <div class="grid grid-cols-2 gap-3">

                <div>

                    <label class="mb-1 block text-sm font-medium text-gray-700">Licznik start</label>

                    <input type="number" wire:model.live.debounce.500ms="odometer_start" class="pilot-field" min="0" @disabled(!$this->editable)>

                </div>

                <div>

                    <label class="mb-1 block text-sm font-medium text-gray-700">Licznik koniec</label>

                    <input type="number" wire:model.live.debounce.500ms="odometer_end" class="pilot-field" min="0" @disabled(!$this->editable)>

                </div>

            </div>

            @if($settlement->odometer_distance !== null)

                <p class="text-sm text-gray-600">Przejechane: <strong>{{ $settlement->odometer_distance }} km</strong></p>

            @endif

        </div>

    </section>



    @if($this->editable)

        <div class="flex flex-wrap gap-3">

            <button type="button" wire:click="save(false)" class="pilot-touch-btn bg-gray-800 text-white">

                Zapisz

            </button>

            <button type="button" wire:click="save(true)" class="pilot-touch-btn bg-teal-600 text-white">

                Zgłoś do biura

            </button>

        </div>

    @endif



    @if($this->showBusCollections)
    <section class="pilot-card">

        <h3 class="mb-1 font-semibold text-gray-900">Zbiórka gotówki w autokarze</h3>

        <p class="mb-3 text-xs text-gray-600">Zaliczki od uczestników zbierane podczas jazdy.</p>

        @livewire('event-bus-collections', [
            'event' => $event,
            'readOnly' => ! $this->editable,
        ], key('pilot-bus-collections-'.$event->id))

    </section>
    @endif



    <section class="pilot-card">

        <h3 class="mb-1 font-semibold text-gray-900">Wydatki pilota</h3>

        <p class="mb-3 text-xs text-gray-600">Tylko pozycje opłacane przez pilota — kwoty faktyczne i skany paragonów.</p>



        @include('pilot.partials.expense-ledger', [

            'editable' => $this->editable,

            'compact' => true,

        ])



        @if($this->editable)

            <div class="mt-4 border-t pt-4">

                <h4 class="mb-2 text-sm font-medium text-gray-800">Wydatek nieprzewidziany</h4>

                <div class="space-y-3">

                    <input type="text" wire:model.live.debounce.500ms="expenseName" placeholder="Opis wydatku" class="pilot-field">

                    <input type="number" step="0.01" wire:model.live.debounce.500ms="expenseAmount" placeholder="Kwota" class="pilot-field">

                    <input type="text" wire:model.live.debounce.500ms="expenseInvoiceNumber" placeholder="Numer faktury (opcjonalnie)" class="pilot-field">

                    <input type="text" wire:model.live.debounce.500ms="expenseNotes" placeholder="Uwagi (opcjonalnie)" class="pilot-field">

                    <button type="button" wire:click="addExpense" class="pilot-touch-btn w-full border border-gray-300 bg-white text-gray-900">

                        Dodaj nieprzewidziany

                    </button>

                </div>

            </div>

        @endif

    </section>



    <section class="pilot-card">

        <h3 class="mb-3 font-semibold text-gray-900">Rozliczenie gotówki</h3>

        @include('pilot.partials.cash-summary', [

            'editable' => $this->editable,

            'compact' => true,

        ])

    </section>



    <section class="pilot-card">

        <h3 class="mb-3 font-semibold text-gray-900">Inne dokumenty</h3>

        @if($this->editable)

            <div class="mb-3 space-y-3">

                <input type="text" wire:model.live.debounce.500ms="documentVendor" placeholder="Wystawca (opcjonalnie)" class="pilot-field">

                <input type="number" step="0.01" wire:model.live.debounce.500ms="documentAmount" placeholder="Kwota dokumentu" class="pilot-field">

                @include('pilot.partials.photo-upload', [
                    'wireModel' => 'documentFiles',
                    'multiple' => true,
                    'compact' => true,
                ])

            </div>

        @endif

        <div class="divide-y rounded-lg border">

            @forelse($documents as $document)

                <div class="flex items-center justify-between gap-3 px-3 py-3 text-sm">

                    <div>

                        <div class="font-medium text-gray-900">{{ $document->vendor_name ?: 'Dokument' }}</div>

                        <div class="text-gray-600">{{ count($document->files ?? []) }} plik(ów)</div>

                    </div>

                    @if($this->editable)

                        <button type="button" wire:click="deleteDocument({{ $document->id }})" class="text-red-600">Usuń</button>

                    @endif

                </div>

            @empty

                <div class="px-3 py-4 text-center text-sm text-gray-500">Brak dokumentów.</div>

            @endforelse

        </div>

    </section>



    <div class="flex flex-wrap gap-3">

        <a href="{{ route('pilot.events.pdf', ['event' => $event, 'audience' => 'folder']) }}" class="pilot-touch-btn border border-gray-300 bg-white text-gray-900">

            Pobierz teczkę PDF

        </a>

    </div>

</div>

