<x-filament-panels::page>
    @php
        $columns = $this->columns();
        $options = \App\Models\Event::getStatusOptions();
    @endphp

    <div class="mb-4 rounded-lg border border-primary-200 bg-primary-50/60 px-4 py-3 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-gray-200">
        <p class="font-medium text-gray-900 dark:text-white">Ścieżka oferty: zapytanie → oferta → rezerwacja wstępna</p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            To kanoniczny widok sprzedaży (nie duplikat listy imprez). Użyj przycisków na karcie, aby zmienić etap. Po kliknięciu
            <span class="font-medium" title="Impreza schodzi ze ścieżki oferty i trafia do obsługi operacyjnej.">Potwierdź</span>
            impreza przechodzi do operacji.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($columns as $status => $events)
            <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-gray-900/40">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3
                        class="text-sm font-semibold text-gray-900 dark:text-white"
                        title="{{ $this->statusTooltip($status) }}"
                    >
                        {{ $options[$status] ?? $status }}
                    </h3>
                    <span
                        class="rounded-md bg-white px-2 py-0.5 text-xs font-medium text-gray-600 shadow-sm dark:bg-gray-800 dark:text-gray-300"
                        title="Liczba imprez na tym etapie"
                    >
                        {{ $events->count() }}
                    </span>
                </div>

                <div class="flex max-h-[70vh] flex-col gap-2 overflow-y-auto">
                    @forelse ($events as $event)
                        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
                            <a
                                href="{{ $this->eventUrl($event) }}"
                                class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                title="Otwórz kartę imprezy"
                            >
                                {{ $event->code ?: '#'.$event->id }} — {{ $event->name }}
                            </a>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $event->client_name ?: '—' }}
                                @if ($event->start_date)
                                    · {{ $event->start_date->format('d.m.Y') }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" title="Osoba odpowiedzialna w biurze">
                                Opiekun: {{ $event->assignedUser?->name ?: '—' }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-1">
                                @foreach ($this->pipelineStatuses() as $target)
                                    @continue($target === $status)
                                    <x-filament::button
                                        size="xs"
                                        color="gray"
                                        wire:click="moveEvent({{ $event->id }}, '{{ $target }}')"
                                        wire:confirm="Zmienić status na «{{ $options[$target] ?? $target }}»?"
                                        title="{{ $this->statusTooltip($target) }}"
                                    >
                                        → {{ $options[$target] ?? $target }}
                                    </x-filament::button>
                                @endforeach
                                <x-filament::button
                                    size="xs"
                                    color="success"
                                    wire:click="moveEvent({{ $event->id }}, '{{ \App\Models\Event::STATUS_CONFIRMED }}')"
                                    wire:confirm="Potwierdzić imprezę?"
                                    title="Potwierdza imprezę i przenosi ją ze ścieżki oferty do obsługi operacyjnej."
                                >
                                    Potwierdź
                                </x-filament::button>
                            </div>
                        </div>
                    @empty
                        <p class="px-1 py-6 text-center text-xs text-gray-400">Brak pozycji</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
