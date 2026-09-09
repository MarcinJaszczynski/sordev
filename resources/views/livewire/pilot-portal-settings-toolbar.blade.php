@php
    $variant = $variant ?? 'full';
    $showShare = in_array($variant, ['full', 'share'], true);
    $showModules = in_array($variant, ['full', 'modules'], true);
    $compact = $variant !== 'full';
@endphp

<div @class([
    'pilot-portal-toolbar',
    'pilot-portal-toolbar--compact' => $compact,
    'mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900' => ! $compact,
])>
    @if ($showShare)
        <div class="flex flex-wrap items-start justify-between gap-3">
            @unless ($compact)
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Udostępnienie portalu</p>
                    @if ($event->shared_with_pilot && $event->shared_with_pilot_at)
                        <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                            Udostępniono: {{ $event->shared_with_pilot_at->format('d.m.Y H:i') }}
                            @if ($event->sharedWithPilotByUser)
                                · {{ $event->sharedWithPilotByUser->name }}
                            @endif
                        </p>
                    @endif
                    @if ($event->pilot_trip_email_sent_at)
                        <p class="mt-1 text-xs text-sky-700 dark:text-sky-300">
                            E-mail: {{ $event->pilot_trip_email_sent_at->format('d.m.Y H:i') }}
                        </p>
                    @elseif ($event->shared_with_pilot)
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                            E-mail do pilota jeszcze nie wysłany.
                        </p>
                    @endif
                </div>
            @endunless

            <div class="flex flex-wrap items-center gap-2">
                @if ($compact && $event->shared_with_pilot)
                    <span class="pilot-badge pilot-badge--success">Udostępniona</span>
                @endif
                @if ($this->previewAsPilotVisible())
                    <a href="{{ $this->previewUrl() }}" target="_blank" rel="noopener"
                       @class([
                           'inline-flex items-center gap-2 rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700',
                           'px-2.5 py-1.5 text-xs' => $compact,
                       ])>
                        {{ $this->previewAsPilotLabel() }}
                    </a>
                @elseif ($this->canPreviewPortal())
                    <span class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
                          title="{{ $this->previewDisabledTitle() }}">
                        Podgląd jako pilot
                    </span>
                @endif
                {{ $this->shareWithPilotAction }}
                {{ $this->sendPilotEmailAction }}
                {{ $this->sharedStatusAction }}
                @if ($variant === 'share')
                    <a
                        href="{{ route('admin.events.pdf', ['event' => $event->id, 'audience' => 'pilot']) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    >PDF</a>
                @endif
                @if ($showModules && $variant === 'full')
                    {{ $this->toggleCurrencyExchangeAction }}
                    {{ $this->toggleBusCollectionsAction }}
                    {{ $this->toggleAttendanceAction }}
                @endif
            </div>
        </div>

        @if ($this->assignPilotHintVisible())
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                {{ $this->assignPilotHintMessage() }}
            </p>
        @endif
    @endif

    @if ($showModules && $variant === 'modules')
        <div class="pilot-modules-bar">
            <span class="pilot-modules-label">Widoczność w portalu</span>
            <div class="flex flex-wrap items-center gap-2">
                {{ $this->toggleCurrencyExchangeAction }}
                {{ $this->toggleBusCollectionsAction }}
                {{ $this->toggleAttendanceAction }}
            </div>
        </div>
    @endif

    @if ($this->migrationHintVisible() && $showModules)
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
            Uruchom migracje bazy (<code class="rounded bg-amber-100 px-1">php artisan migrate</code>), aby włączyć przełączniki widoczności modułów.
        </p>
    @endif

    <x-filament-actions::modals />
</div>
