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

    {{-- Zaliczka od biura (podgląd) — bez drugiego cash-desku --}}
    <section class="pilot-card">
        <h3 class="mb-1 font-semibold text-gray-900">Zaliczka od biura</h3>
        <p class="mb-3 text-xs text-gray-600">Gotówka przekazana Ci przed wyjazdem — zasila saldo poniżej.</p>

        @if($this->paidAdvanceLines->isNotEmpty())
            <ul class="divide-y rounded-lg border">
                @foreach($this->paidAdvanceLines as $line)
                    @php
                        $symbol = $line['currency']?->symbol ?? $line['currency']?->code ?? '—';
                    @endphp
                    <li class="flex items-center justify-between px-3 py-2 text-sm">
                        <span class="text-gray-700">{{ $line['currency']?->name ?? 'Waluta' }}</span>
                        <span class="font-semibold text-gray-900">
                            {{ \App\Support\MoneyFormatter::format($line['amount'], $symbol) }}
                        </span>
                    </li>
                @endforeach
            </ul>
            @if($event->pilot_advance_paid_comment)
                <p class="mt-2 text-xs text-gray-600">
                    <span class="font-medium">Komentarz biura:</span> {{ $event->pilot_advance_paid_comment }}
                </p>
            @endif
        @elseif($this->plannedAdvanceLines->isNotEmpty())
            <div class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                <p class="font-medium">Zaplanowana (jeszcze niewypłacona)</p>
                <ul class="mt-1 space-y-0.5">
                    @foreach($this->plannedAdvanceLines as $line)
                        <li>
                            {{ \App\Support\MoneyFormatter::format($line->amount, $line->currency?->symbol ?? $line->currency?->code ?? 'PLN') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="text-sm text-gray-500">Biuro nie zapisało jeszcze wypłaty gotówki.</p>
        @endif
    </section>

    {{-- Jedyny cash desk: saldo, wymiana, wydatki, zwrot --}}
    @livewire('pilot-cash-desk', [
        'event' => $event,
        'context' => 'pilot',
        'compact' => true,
        'showOfficePayoutBlock' => false,
    ], key('pilot-cash-desk-settlement-'.$event->id))

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
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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

    <section class="pilot-card">
        <h3 class="mb-3 font-semibold text-gray-900">Inne dokumenty</h3>
        @if($this->editable)
            <div class="mb-3 space-y-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-600">Typ dokumentu</label>
                    <select wire:model="documentType" class="pilot-field">
                        @foreach (\App\Models\EventSettlementDocument::$documentTypes as $typeKey => $typeLabel)
                            <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="text" wire:model.live.debounce.500ms="documentNumber" placeholder="Numer dokumentu (opcjonalnie)" class="pilot-field">
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
            @if($documents->isNotEmpty())
                <div class="px-3 py-3">
                    @include('pilot.partials.document-list', [
                        'documents' => $documents,
                        'editable' => $this->editable,
                        'compact' => true,
                    ])
                </div>
            @else
                <div class="px-3 py-4 text-center text-sm text-gray-500">Brak dokumentów.</div>
            @endif
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('pilot.events.pdf', ['event' => $event, 'audience' => 'folder']) }}" class="pilot-touch-btn border border-gray-300 bg-white text-gray-900">
            Pobierz teczkę PDF
        </a>
    </div>
</div>
