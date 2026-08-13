<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Kontakt',
    ])

    @if($this->readOnly)
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
            Podgląd tylko do odczytu — wysyłanie zapytań jest wyłączone.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="client-portal-section overflow-hidden !p-0">
            <header class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-900">Twoje zapytania</h3>
            </header>
            @if($this->inquiries === [])
                <p class="px-4 py-4 text-sm text-slate-500">Brak wysłanych zapytań.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($this->inquiries as $inquiry)
                        <li class="px-4 py-4" wire:key="pilot-inquiry-{{ $inquiry->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $inquiry->subject }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $inquiry->created_at?->format('d.m.Y H:i') }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                    {{ $inquiry->status_label }}
                                </span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ $inquiry->body }}</p>
                            @if(filled($inquiry->office_reply))
                                <div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50/70 px-3 py-2 text-sm text-emerald-950">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Odpowiedź biura</p>
                                    <p class="mt-1 whitespace-pre-wrap">{{ $inquiry->office_reply }}</p>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="client-portal-section space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">Nowe zapytanie</h3>
            <div>
                <label class="mb-1 block text-xs text-slate-600">Temat</label>
                <input type="text" wire:model="formSubject" @disabled($this->readOnly)
                       class="fi-input w-full rounded-lg border-slate-300 text-sm" maxlength="255" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-600">Treść</label>
                <textarea wire:model="formBody" rows="6" @disabled($this->readOnly)
                          class="fi-input w-full rounded-lg border-slate-300 text-sm" maxlength="5000"></textarea>
            </div>
            <x-filament::button wire:click="send" color="primary" class="w-full" :disabled="$this->readOnly">
                Wyślij do biura
            </x-filament::button>
        </section>
    </div>
</x-filament-panels::page>
