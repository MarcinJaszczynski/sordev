@php
    $insuranceSelection = old('travel_insurance', data_get($flow ?? [], 'travel_insurance'));
    $isContractAnnex = ($agreement ?? null) instanceof \App\Models\Contract
        && method_exists($agreement, 'isAnnex')
        && $agreement->isAnnex();
@endphp

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Umowa online - Plan wycieczki</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/reservation.css') }}">
</head>
<body class="bg-light">
<div class="container py-4 py-md-5 reservation-page">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    @include('front.agreements._stepper', ['agreement' => $agreement, 'active' => 1])

                    <div class="row g-4 align-items-start mt-1">
                        <div class="col-lg-8">
                            <h2>Plan wycieczki</h2>

                            <div class="white_box mb-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                    <div><strong>Numer umowy:</strong> {{ $agreement->agreement_number ?: ('#' . $agreement->id) }}</div>
                                    <div><strong>Typ:</strong> {{ $agreement->agreement_type_label }}</div>
                                </div>
                                <div><strong>Impreza:</strong> {{ $agreement->event_name ?: ($agreement->event?->name ?? '—') }}</div>
                                <div><strong>Termin:</strong> {{ optional($agreement->event_start_date)->format('d.m.Y') ?: '—' }} - {{ optional($agreement->event_end_date)->format('d.m.Y') ?: '—' }}</div>
                                @php($orderingPartyService = app(\App\Services\ContractOrderingPartyService::class))
                                @php($orderingParties = $orderingPartyService->partiesForTemplatePayload($agreement))
                                <div><strong>Zamawiający:</strong>
                                    @if(count($orderingParties) > 1)
                                        <ul class="mb-0 mt-1">
                                            @foreach($orderingParties as $party)
                                                <li>
                                                    {{ $party['name'] }}
                                                    @if(filled($party['email']) || filled($party['phone']))
                                                        <span class="text-muted">({{ collect([$party['email'] ?? null, $party['phone'] ?? null])->filter()->implode(', ') }})</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        {{ $orderingPartyService->formattedPartyNames($agreement) }}
                                    @endif
                                </div>
                                @if(filled($agreement->ordering_party_notes))
                                    <div><strong>Uwagi do zamawiających:</strong> {{ $agreement->ordering_party_notes }}</div>
                                @endif
                                @if($agreement->isIndividual())
                                    <div><strong>Uczestnik:</strong> {{ $agreement->participant_name ?: ($agreement->participantPayment?->participant_name ?? '—') }}</div>
                                @endif
                                @php($groupPricing = app(\App\Services\ContractGroupPricingService::class)->presentationFor($agreement))
                                @if($groupPricing['is_group'] ?? false)
                                    <div><strong>Liczba uczestników:</strong> {{ $groupPricing['participant_count'] }}</div>
                                    <div><strong>Cena za osobę:</strong> {{ number_format((float) $groupPricing['unit_price'], 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</div>
                                    <div><strong>Schemat płatności:</strong> {{ $groupPricing['payment_scheme_label'] }}</div>
                                @endif
                                <div><strong>Kwota do zapłaty:</strong> {{ number_format((float) ($groupPricing['total_amount'] ?? $agreement->amount_due), 2, ',', ' ') }} {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</div>
                                @if(($groupPricing['is_group'] ?? false) && !empty($groupPricing['payment_schedules']))
                                    <div class="mt-2">
                                        <strong>Harmonogram płatności:</strong>
                                        <ul class="mb-0 mt-1">
                                            @foreach($groupPricing['payment_schedules'] as $schedule)
                                                <li>
                                                    {{ $schedule['label'] ?: 'Transza' }}:
                                                    {{ number_format((float) $schedule['amount'], 2, ',', ' ') }} PLN
                                                    @if(filled($schedule['due_date']))
                                                        <span class="text-muted">(termin: {{ $schedule['due_date'] }})</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div class="white_box">
                                @php
                                    $annexService = app(\App\Services\ContractAnnexService::class);
                                @endphp
                                <h4 class="mb-3">{{ $isContractAnnex ? 'Treść aneksu' : 'Treść umowy' }}</h4>

                                @if($isContractAnnex)
                                    <div class="mb-3 small">
                                        @if($agreement->parentContract?->agreement_number)
                                            <div><strong>Umowa pierwotna:</strong> {{ $agreement->parentContract->agreement_number }}</div>
                                        @endif
                                        @if(!empty($agreement->annex_change_types))
                                            <div><strong>Rodzaje zmian:</strong> {{ $annexService->formatChangeTypesLabels((array) $agreement->annex_change_types) }}</div>
                                        @endif
                                    </div>
                                @endif

                                @if($agreement->usesUploadedAgreementDocument())
                                    <div class="border rounded bg-white overflow-hidden" style="max-height: 480px;">
                                        <iframe
                                            src="{{ $agreement->custom_agreement_document_url }}#toolbar=0"
                                            title="Dokument umowy"
                                            class="w-100"
                                            style="min-height: 420px; border: 0;"
                                        ></iframe>
                                    </div>
                                    <p class="mt-2 mb-0">
                                        <a href="{{ $agreement->custom_agreement_document_url }}" target="_blank" rel="noopener">
                                            Pobierz dokument PDF
                                        </a>
                                    </p>
                                @elseif(filled($agreement->agreement_body))
                                    <div class="border rounded p-3 bg-white agreement-body-preview">{!! $agreement->agreement_body !!}</div>
                                @else
                                    <p class="text-muted mb-0">Treść {{ $isContractAnnex ? 'aneksu' : 'umowy' }} nie została jeszcze przygotowana.</p>
                                @endif

                                @if($isContractAnnex && $agreement->hasAnnexProgramChange())
                                    <div class="mt-3 border rounded p-3 bg-white">
                                        <h5 class="mb-2">Program imprezy po zmianie</h5>
                                        @if(filled($agreement->annex_program_change_notes))
                                            <p class="small text-muted">{{ $agreement->annex_program_change_notes }}</p>
                                        @endif
                                        {!! $annexService->formatProgramSnapshotHtml($agreement->annex_program_snapshot) !!}
                                    </div>
                                @endif

                                @if(!empty($agreement->attachments))
                                    <div class="mt-3">
                                        <h5 class="mb-2">Załączniki do umowy</h5>
                                        <ul class="mb-0">
                                            @foreach((array) $agreement->attachments as $file)
                                                <li>
                                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($file) }}" target="_blank" rel="noopener">
                                                        {{ basename($file) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="white_box reservation-info-card mb-3">
                                <h4>Informacje o imprezie</h4>
                                <p class="mb-1"><strong>Status umowy:</strong> {{ $agreement->status_label }}</p>
                                <p class="mb-1"><strong>Status płatności:</strong> {{ $agreement->payment_status_label }}</p>
                                <p class="mb-1"><strong>Liczba uczestników:</strong> {{ $agreement->participant_count ?: '—' }}</p>
                                <p class="mb-0"><strong>Waluta:</strong> {{ strtoupper((string) ($agreement->currency ?: 'PLN')) }}</p>
                            </div>

                            <form method="POST" action="{{ route('agreement.flow.plan', ['token' => $agreement->public_token]) }}" class="white_box reservation-info-card">
                                @csrf
                                <h4>Dodatkowe ubezpieczenie</h4>
                                <p class="small text-muted">Wybierz jedną z opcji, aby przejść do kolejnego kroku.</p>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="radio" name="travel_insurance" id="travel_insurance_no" value="no" {{ $insuranceSelection === 'no' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="travel_insurance_no">
                                        Nie, nie chcę dodatkowego ubezpieczenia.
                                    </label>
                                </div>

                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="travel_insurance" id="travel_insurance_yes" value="yes" {{ $insuranceSelection === 'yes' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="travel_insurance_yes">
                                        Tak, chcę dodatkowe ubezpieczenie „Bezpieczne Rezerwacje”.
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-danger btn-block mt-4">Potwierdź</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
