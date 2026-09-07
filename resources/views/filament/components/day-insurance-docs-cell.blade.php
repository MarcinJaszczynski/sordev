@php
    /** @var \App\Models\EventDayInsurance|null $record */
    $record = $getRecord();
    $policy = $record?->policy;

    $policyPath = \App\Models\Event::normalizeInsuranceDocumentPath($policy?->document_path);
    $listPath = \App\Models\Event::normalizeInsuranceDocumentPath($policy?->insured_list_path);

    $policyUrl = $policyPath ? \App\Support\StoragePath::publicUrl($policyPath) : null;
    $listUrl = $listPath ? \App\Support\StoragePath::publicUrl($listPath) : null;

    $badgeClass = 'inline-flex max-w-[7rem] items-center justify-center gap-1 truncate rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-200';
@endphp

@if (! $record || (! $policyUrl && ! $listUrl))
    <span class="text-gray-400 dark:text-gray-500">—</span>
@else
    <div class="inline-flex flex-wrap items-center justify-center gap-1" onclick="event.stopPropagation()">
        @if ($policyUrl)
            <a
                href="{{ $policyUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="{{ $badgeClass }}"
                title="Otwórz plik polisy"
            >
                <span aria-hidden="true">📎</span>
                <span>Polisa</span>
            </a>
        @endif
        @if ($listUrl)
            <a
                href="{{ $listUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="{{ $badgeClass }}"
                title="Otwórz listę ubezpieczonych"
            >
                <span aria-hidden="true">📎</span>
                <span>Lista</span>
            </a>
        @endif
    </div>
@endif
