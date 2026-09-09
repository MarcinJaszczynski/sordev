@php
    /** @var array<string, mixed> $info */
    $hasPdf = (bool) ($info['has_pdf'] ?? false);
    $number = (string) ($info['contract_number'] ?? '—');
    $formLabel = (string) ($info['settlement_form_label'] ?? '—');
    $generatedAt = (string) ($info['generated_at'] ?? '—');
    $sentAt = (string) ($info['sent_at'] ?? null);
    $shared = (bool) ($info['shared_in_portal'] ?? false);
    $canGenerate = (bool) ($info['can_generate'] ?? false);
    $hint = (string) ($info['hint'] ?? '');
@endphp

<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900/40">
    @if (! $canGenerate)
        <p class="text-amber-800 dark:text-amber-200">{{ $hint !== '' ? $hint : 'Przypisz kontrahenta-pilota, aby wygenerować umowę.' }}</p>
    @elseif (! $hasPdf)
        <p class="text-gray-700 dark:text-gray-200">Brak wygenerowanej umowy. Uzupełnij honorarium (opcjonalnie) i kliknij «Generuj PDF».</p>
    @else
        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $number }} · {{ $formLabel }}</p>
        <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
            Wygenerowano: {{ $generatedAt }}
            @if ($sentAt)
                · Wysłano: {{ $sentAt }}
            @endif
            · Panel: {{ $shared ? 'udostępniona' : 'nieudostępniona' }}
        </p>
    @endif
</div>
