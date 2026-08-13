@php
    $record = $getRecord();
@endphp

@if ($record)
<div class="mb-6 space-y-3">
    <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400">
            <x-heroicon-m-check-badge class="h-6 w-6" />
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Status operacyjny imprezy</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Kliknij kartę, aby przejść do uzupełnienia</p>
        </div>
    </div>

    @include('filament.components.event-readiness-overview', ['event' => $record])
</div>
@endif
