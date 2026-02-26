@php
    $total = $progress['total'] ?? 0;
    $processed = $progress['processed'] ?? 0;
    $errors = $progress['errors'] ?? 0;
    $finished = $progress['finished'] ?? false;
    $pct = $total > 0 ? min(100, round(($processed / $total) * 100)) : 0;
@endphp

<div wire:poll.5s>
    @if($visible || ($total > 0))
        <div x-data class="fixed bottom-6 right-6 z-50" aria-live="polite">
            <div class="max-w-xs w-80 bg-white shadow-lg rounded-lg border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 border-b">
                    <div class="text-sm font-medium text-gray-800">Przeliczanie cen</div>
                    <div class="flex items-center space-x-2">
                        @if($finished)
                            <span class="text-sm text-green-600">Zakończono</span>
                        @endif
                        <button wire:click="closeToast" class="text-gray-400 hover:text-gray-600 text-sm px-2 py-1">✕</button>
                    </div>
                </div>

                <div class="p-3">
                    <div class="flex items-center justify-between mb-2 text-sm text-gray-700">
                        <div>Postęp</div>
                        <div class="text-xs text-gray-500">{{ $processed }} z {{ $total }}</div>
                    </div>

                    <div class="w-full bg-gray-200 h-3 rounded overflow-hidden mb-2">
                        <div class="h-3 bg-green-500 transition-all" style="width: {{ $pct }}%"></div>
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-600">
                        <div>Błędy: {{ $errors }}</div>
                        <div>{{ $pct }}%</div>
                    </div>

                    @if($finished)
                        <div class="mt-3 text-sm text-gray-700">Przeliczanie zakończone. Możesz zamknąć to okienko.</div>
                    @else
                        <div class="mt-3 text-sm text-gray-600">Zadanie działa w tle. Otwórz listę powiadomień, by zobaczyć szczegóły.</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
