@php
    use App\Filament\Resources\EventResource;
    use App\Filament\Resources\LegacyEventResource;
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-50 to-cyan-50 p-4">
        <h3 class="text-sm font-semibold text-slate-900">Kryteria dopasowania</h3>
        <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-700 md:grid-cols-3">
            <div class="rounded-lg bg-white/80 px-3 py-2">
                <span class="font-medium">Email:</span>
                <span class="ml-1">{{ $criteria['email'] ?? 'brak' }}</span>
            </div>
            <div class="rounded-lg bg-white/80 px-3 py-2">
                <span class="font-medium">Telefon:</span>
                <span class="ml-1">{{ $criteria['phone'] ?? 'brak' }}</span>
            </div>
            <div class="rounded-lg bg-white/80 px-3 py-2">
                <span class="font-medium">Nazwa:</span>
                <span class="ml-1">{{ $criteria['name'] ?? 'brak' }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-slate-900">Imprezy aktywne (Event)</h4>
                <span class="rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-800">{{ $events->count() }}</span>
            </div>

            @if ($events->isEmpty())
                <p class="text-sm text-slate-500">Brak powiązanych imprez w tabeli Event.</p>
            @else
                <div class="space-y-2">
                    @foreach ($events as $event)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $event->name }}</p>
                                    <p class="text-xs text-slate-600">{{ $event->client_name ?: 'Bez nazwy klienta' }}</p>
                                </div>
                                <a
                                    href="{{ EventResource::getUrl('edit', ['record' => $event->id]) }}"
                                    class="rounded-lg border border-cyan-300 px-2 py-1 text-xs font-semibold text-cyan-700 hover:bg-cyan-50"
                                >
                                    Otwórz
                                </a>
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-slate-600 sm:grid-cols-2">
                                <span><strong>Start:</strong> {{ optional($event->start_date)->format('d.m.Y') }}</span>
                                <span><strong>Koniec:</strong> {{ optional($event->end_date)->format('d.m.Y') ?: '—' }}</span>
                                <span><strong>Status:</strong> {{ $event->status }}</span>
                                <span><strong>Uczestnicy:</strong> {{ $event->participant_count ?? '—' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-slate-900">Imprezy archiwalne (LegacyEvent)</h4>
                <span class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800">{{ $legacyEvents->count() }}</span>
            </div>

            @if ($legacyEvents->isEmpty())
                <p class="text-sm text-slate-500">Brak powiązanych imprez w archiwum.</p>
            @else
                <div class="space-y-2">
                    @foreach ($legacyEvents as $legacyEvent)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $legacyEvent->name }}</p>
                                    <p class="text-xs text-slate-600">{{ $legacyEvent->client_name ?: 'Bez nazwy klienta' }}</p>
                                </div>
                                <a
                                    href="{{ LegacyEventResource::getUrl('view', ['record' => $legacyEvent->id]) }}"
                                    class="rounded-lg border border-indigo-300 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                >
                                    Otwórz
                                </a>
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-slate-600 sm:grid-cols-2">
                                <span><strong>Wyjazd:</strong> {{ optional($legacyEvent->start_datetime)->format('d.m.Y H:i') }}</span>
                                <span><strong>Powrót:</strong> {{ optional($legacyEvent->end_datetime)->format('d.m.Y H:i') ?: '—' }}</span>
                                <span><strong>Status:</strong> {{ $legacyEvent->legacy_status ?: '—' }}</span>
                                <span><strong>Kod:</strong> {{ $legacyEvent->office_id ?: '—' }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
