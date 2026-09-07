<div class="hotel-card">
    <div class="hotel-card-header mb-4">
        <div>
            <h3 class="hotel-card-title">Log korespondencji z obiektem</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Ręczny zapis rozmów, maili i ustaleń z hotelem — bez integracji e-mail.
            </p>
        </div>
    </div>

    @if ($logs->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Brak zapisanej korespondencji dla tej imprezy.</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($logs as $log)
                <li class="py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span @class([
                                    'rounded-full px-2 py-0.5 font-semibold',
                                    'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' => $log->direction === 'inbound',
                                    'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200' => $log->direction === 'outbound',
                                ])>
                                    {{ $directions[$log->direction] ?? $log->direction }}
                                </span>
                                <span class="text-gray-500">{{ $log->contacted_at?->format('d.m.Y H:i') }}</span>
                                @if ($log->contractor?->name)
                                    <span class="text-gray-600 dark:text-gray-300">{{ $log->contractor->name }}</span>
                                @endif
                                @if ($log->hotelStay)
                                    <span class="text-gray-500">noc {{ $log->hotelStay->day }}</span>
                                @endif
                            </div>
                            @if ($log->subject)
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $log->subject }}</p>
                            @endif
                            @if ($log->body)
                                <p class="mt-1 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $log->body }}</p>
                            @endif
                            @if ($log->contact_person)
                                <p class="mt-1 text-xs text-gray-500">Kontakt: {{ $log->contact_person }}</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-400">{{ $log->author?->name }} • {{ $log->created_at?->diffForHumans() }}</p>
                        </div>
                        @if ($url = $service->attachmentUrl($log))
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary-600 hover:underline">
                                Załącznik
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <details class="hotel-accordion">
        <summary>Dodaj wpis korespondencji</summary>
        <form wire:submit.prevent="save" class="mt-3 space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/40">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Kierunek</label>
                    <select wire:model.live.debounce.500ms="direction" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        @foreach ($directions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Data kontaktu</label>
                    <input type="datetime-local" wire:model.live.debounce.500ms="contactedAt" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Hotel / kontrahent</label>
                    <select wire:model.live.debounce.500ms="contractorId" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">— wybierz —</option>
                        @foreach ($contractorOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Noc (opcjonalnie)</label>
                    <select wire:model.live.debounce.500ms="eventHotelStayId" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">— cała impreza —</option>
                        @foreach ($stayOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Osoba kontaktowa</label>
                    <input type="text" wire:model.live.debounce.500ms="contactPerson" placeholder="np. recepcja, opiekun grupy" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Temat</label>
                    <input type="text" wire:model.live.debounce.500ms="subject" placeholder="np. Potwierdzenie listy pokoi" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Treść / ustalenia</label>
                    <textarea wire:model.live.debounce.500ms="body" rows="3" class="w-full rounded-lg border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">Załącznik (opcjonalnie)</label>
                    <input type="file" wire:model.live.debounce.500ms="attachment" class="w-full text-sm">
                    <div wire:loading wire:target="attachment" class="mt-1 text-xs text-gray-500">Wgrywanie…</div>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                    Dodaj wpis
                </button>
            </div>
        </form>
    </details>
</div>
