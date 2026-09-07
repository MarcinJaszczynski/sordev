<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $event->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Umowa',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--accent">
            {{ $archiveMessage }}
        </div>
    @endif

    @if(! $contract)
        <div class="client-portal-section text-sm text-[#5F5E5A]">
            Brak umowy powiązanej z Twoim kontem dla tej wycieczki.
        </div>
    @else
        <div class="space-y-4">
            <section class="client-portal-section">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[#2C2C2A]">{{ $contract->title ?: 'Umowa' }}</h3>
                        <p class="mt-1 text-sm text-[#888780]">
                            Nr {{ $contract->agreement_number ?: $contract->id }}
                            · {{ $contract->status_label ?? $contract->status }}
                        </p>
                    </div>
                    @if($pdfUrl)
                        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 rounded-lg bg-[#2C2C2A] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1f1f1d]">
                            Pobierz PDF
                        </a>
                    @endif
                </div>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-[#888780]">Zamawiający</dt>
                        <dd class="font-medium">{{ $contract->customer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#888780]">Podpisujący</dt>
                        <dd class="font-medium">{{ $contract->signer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#888780]">Uczestnik</dt>
                        <dd class="font-medium">{{ $contract->participant_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[#888780]">Status płatności</dt>
                        <dd class="font-medium">{{ $contract->payment_status_label ?? $contract->payment_status }}</dd>
                    </div>
                </dl>
            </section>

            @if(filled($contract->agreement_body))
                <section class="client-portal-section prose prose-sm max-w-none">
                    {!! \App\Support\AgreementHtml::sanitize((string) $contract->agreement_body) !!}
                </section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
