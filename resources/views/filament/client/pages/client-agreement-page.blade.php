<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    @if(! $contract)
        <div class="sor-lw-card text-sm text-gray-600">
            Brak umowy powiązanej z Twoim kontem dla tej wycieczki.
        </div>
    @else
        <div class="space-y-4">
            <section class="sor-lw-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="sor-lw-title">{{ $contract->title ?: 'Umowa' }}</h3>
                        <p class="sor-lw-muted mt-1">
                            Nr {{ $contract->agreement_number ?: $contract->id }}
                            · {{ $contract->status_label ?? $contract->status }}
                        </p>
                    </div>
                    @if($pdfUrl)
                        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Pobierz PDF
                        </a>
                    @endif
                </div>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Zamawiający</dt>
                        <dd class="font-medium">{{ $contract->customer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Podpisujący</dt>
                        <dd class="font-medium">{{ $contract->signer_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Uczestnik</dt>
                        <dd class="font-medium">{{ $contract->participant_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status płatności</dt>
                        <dd class="font-medium">{{ $contract->payment_status_label ?? $contract->payment_status }}</dd>
                    </div>
                </dl>
            </section>

            @if(filled($contract->agreement_body))
                <section class="sor-lw-card prose prose-sm max-w-none dark:prose-invert">
                    {!! $contract->agreement_body !!}
                </section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
