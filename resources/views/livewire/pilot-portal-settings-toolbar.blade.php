<div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
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

        <div class="flex flex-wrap gap-2">
            @if ($this->previewAsPilotVisible())
                <a href="{{ $this->previewUrl() }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                    {{ $this->previewAsPilotLabel() }}
                </a>
            @elseif ($this->canPreviewPortal())
                <span class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
                      title="Najpierw przypisz pilota poniżej">
                    Podgląd jako pilot
                </span>
            @endif
            {{ $this->shareWithPilotAction }}
            {{ $this->sendPilotEmailAction }}
            {{ $this->sharedStatusAction }}
            {{ $this->toggleCurrencyExchangeAction }}
            {{ $this->toggleBusCollectionsAction }}
        </div>
    </div>

    @if ($this->assignPilotHintVisible())
        <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
            Przypisz pilota poniżej, aby udostępnić wycieczkę i otworzyć podgląd jego panelu.
        </p>
    @endif

    @if ($this->migrationHintVisible())
        <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
            Uruchom migracje bazy (<code class="rounded bg-amber-100 px-1">php artisan migrate</code>), aby włączyć przełączniki widoczności modułów.
        </p>
    @endif

    <x-filament-actions::modals />
</div>
