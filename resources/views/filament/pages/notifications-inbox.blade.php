<x-filament-panels::page>
    @php
        $data = $this->inboxData;
        $items = $data['items'] ?? [];
        $counts = $data['counts'] ?? [];
        $typeLabels = $data['type_labels'] ?? [];
        $typeBadge = [
            'task' => 'Zadanie',
            'comment' => 'Komentarz',
            'new_event' => 'Nowa impreza',
            'event' => 'Impreza',
            'pending_cancellation_event' => 'Do anulacji',
            'invoice_request' => 'Wniosek o fakturę',
            'message' => 'Wiadomość',
        ];
        $colorClasses = [
            'violet' => 'border-violet-300 bg-violet-50 text-violet-900 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100',
            'sky' => 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-100',
            'amber' => 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
            'blue' => 'border-blue-300 bg-blue-50 text-blue-900 dark:border-blue-700 dark:bg-blue-950/40 dark:text-blue-100',
            'rose' => 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-100',
            'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-900 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-100',
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Zadania: {{ $counts['tasks'] ?? 0 }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Komentarze: {{ $counts['comments'] ?? 0 }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Nowe imprezy: {{ $counts['new_events'] ?? 0 }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Wnioski o fakturę: {{ $counts['invoice_requests'] ?? 0 }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Wiadomości: {{ $counts['messages'] ?? 0 }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="refreshInbox" color="gray" size="sm" icon="heroicon-o-arrow-path">
                    Odśwież
                </x-filament::button>
                <x-filament::button wire:click="markAllRead" color="gray" size="sm" icon="heroicon-o-check-badge">
                    Oznacz wszystkie jako przeczytane
                </x-filament::button>
            </div>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                @foreach ($typeLabels as $typeKey => $label)
                    <button
                        type="button"
                        wire:click="$set('typeFilter', '{{ $typeKey }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-primary-600 text-white' => $typeFilter === $typeKey,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $typeFilter !== $typeKey,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" wire:model.live="unreadOnly" class="rounded border-gray-400">
                Tylko nieprzeczytane
            </label>
        </div>

        @if ($items === [])
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-400">
                Brak powiadomień dla wybranych filtrów.
            </div>
        @else
            <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-900">
                @foreach ($items as $item)
                    @php
                        $isRead = (bool) ($item['is_read'] ?? false);
                        $color = $item['color'] ?? 'blue';
                        $chipClass = $colorClasses[$color] ?? $colorClasses['blue'];
                        $type = $item['type'] ?? 'event';
                    @endphp
                    <li @class([
                        'flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between',
                        'opacity-60' => $isRead,
                    ])>
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $chipClass }}">
                                    {{ $typeBadge[$type] ?? $type }}
                                </span>
                                @if ($isRead)
                                    <span class="text-xs text-gray-500">Przeczytane</span>
                                @endif
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $item['title'] ?? 'Powiadomienie' }}
                            </p>
                            @if (! empty($item['meta']))
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['meta'] }}</p>
                            @endif
                            <p class="text-xs text-gray-500">{{ $item['time'] ?? '' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if (! empty($item['url']))
                                <x-filament::button
                                    tag="a"
                                    href="{{ $item['url'] }}"
                                    size="sm"
                                    color="primary"
                                >
                                    Otwórz
                                </x-filament::button>
                            @endif
                            @if (! $isRead && ! empty($item['fingerprint']))
                                <x-filament::button
                                    wire:click="markRead('{{ $item['fingerprint'] }}')"
                                    size="sm"
                                    color="gray"
                                >
                                    Przeczytane
                                </x-filament::button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
