<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Kontakt',
    ])

    @if($this->readOnly)
        <div class="portal-notice portal-notice--accent">
            Podgląd tylko do odczytu — wysyłanie zapytań jest wyłączone.
        </div>
    @endif

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="portal-card !p-0 overflow-hidden">
            <header class="border-b border-[#E5E3DA] bg-[#F1EFE8] px-4 py-3">
                <h3 class="text-sm font-medium text-[#2C2C2A]">Twoje zapytania</h3>
            </header>
            @if($this->inquiries === [])
                <p class="px-4 py-4 portal-muted">Brak wysłanych zapytań.</p>
            @else
                <ul class="divide-y divide-[#E5E3DA]">
                    @foreach($this->inquiries as $inquiry)
                        <li class="px-4 py-4" wire:key="inquiry-{{ $inquiry->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-[#2C2C2A]">{{ $inquiry->subject }}</p>
                                    <p class="mt-1 text-xs text-[#888780]">{{ $inquiry->created_at?->format('d.m.Y H:i') }}</p>
                                </div>
                                <span class="portal-status-pill">{{ $inquiry->status_label }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-[#5F5E5A]">{{ $inquiry->body }}</p>
                            @if(filled($inquiry->office_reply))
                                <div class="mt-3 rounded-lg border border-[#E5E3DA] bg-[#EAF3DE] px-3 py-2 text-sm text-[#27500A]">
                                    <p class="text-xs font-semibold uppercase tracking-wide">Odpowiedź biura</p>
                                    <p class="mt-1 whitespace-pre-wrap">{{ $inquiry->office_reply }}</p>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="portal-card space-y-3">
            <div class="portal-card-title !mb-0"><p>Nowe zapytanie</p></div>
            <div>
                <label class="mb-1 block text-xs text-[#888780]">Temat</label>
                <input type="text" wire:model="formSubject" @disabled($this->readOnly)
                       class="fi-input w-full rounded-lg border-[#D3D1C7] text-sm" maxlength="255" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#888780]">Treść</label>
                <textarea wire:model="formBody" rows="6" @disabled($this->readOnly)
                          class="fi-input w-full rounded-lg border-[#D3D1C7] text-sm" maxlength="5000"></textarea>
            </div>
            <button type="button" wire:click="send" class="portal-btn-primary w-full justify-center" @disabled($this->readOnly)>
                Wyślij do biura
            </button>
        </section>
    </div>
</x-filament-panels::page>
