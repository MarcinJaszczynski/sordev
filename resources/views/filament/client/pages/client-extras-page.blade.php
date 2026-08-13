<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Świadczenia',
    ])

    @if($this->readOnly)
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            Podgląd tylko do odczytu — zapis świadczeń jest wyłączony.
        </div>
    @endif

    @if($this->catalog === [])
        <div class="client-portal-section text-sm text-slate-600">
            Biuro nie zdefiniowało dodatkowych świadczeń na umowie.
        </div>
    @elseif($this->participants === [])
        <div class="client-portal-section text-sm text-slate-600">
            Brak uczestników do przypisania świadczeń.
        </div>
    @else
        <div class="space-y-4">
            @foreach($this->participants as $participant)
                <section class="client-portal-section" wire:key="extras-p-{{ $participant->id }}">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $participant->fullName() ?: 'Uczestnik #'.$participant->id }}</h3>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach($this->catalog as $extra)
                            @if(($extra['applies'] ?? 'participant') !== 'participant')
                                @continue
                            @endif
                            @php
                                $key = $extra['key'];
                                $options = $extra['options'] ?? [];
                            @endphp
                            <div>
                                <label class="mb-1 block text-xs text-slate-600">
                                    {{ $extra['label'] }}
                                    @if(($extra['per_unit_pln'] ?? 0) > 0)
                                        <span class="text-[#0663fc]">(+{{ number_format($extra['per_unit_pln'], 2, ',', ' ') }} PLN)</span>
                                    @endif
                                </label>
                                @if(count($options) > 0)
                                    <select
                                        wire:model="selections.{{ $participant->id }}.{{ $key }}"
                                        @disabled($this->readOnly)
                                        class="fi-input w-full rounded-lg border-slate-300 text-sm"
                                    >
                                        <option value="">Bez wyboru</option>
                                        @foreach($options as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="text"
                                        wire:model="selections.{{ $participant->id }}.{{ $key }}"
                                        @disabled($this->readOnly)
                                        class="fi-input w-full rounded-lg border-slate-300 text-sm"
                                        placeholder="Wpisz szczegóły lub zostaw puste"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <x-filament::button wire:click="save" color="primary" :disabled="$this->readOnly">
                Zapisz świadczenia
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
