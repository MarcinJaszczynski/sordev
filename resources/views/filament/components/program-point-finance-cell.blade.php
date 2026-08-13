@php
    /** @var string $calc */
    /** @var string $planned */
    /** @var string $paid */
    /** @var string $paidStatus */
    /** @var string|null $paymentHint */
    /** @var string|null $pilotDueHint */
    /** @var string|null $remainingHint */
    /** @var string|null $documentHint */
    /** @var bool $hasUploadedFile */
    /** @var string|null $statusLabel */
    /** @var string|null $statusColor */
    $paymentHint = $paymentHint ?? null;
    $hasUploadedFile = $hasUploadedFile ?? false;
    $statusLabel = $statusLabel ?? null;
@endphp

<div class="epp-finance-cell text-right text-xs leading-snug">
    @if (! empty($hideSetParentFinance))
        <span class="text-gray-400">—</span>
    @else
        <div class="tabular-nums text-gray-600 dark:text-gray-300">
            <span class="text-[10px] uppercase tracking-wide text-gray-400">Kalk.</span>
            {{ $calc }}
        </div>
        <div class="mt-0.5 font-medium tabular-nums text-gray-900 dark:text-gray-100">
            <span class="text-[10px] font-normal uppercase tracking-wide text-gray-400">Plan</span>
            {{ $planned }}
        </div>
        <div @class([
            'mt-0.5 tabular-nums font-semibold',
            'text-emerald-700 dark:text-emerald-300' => $paidStatus === 'full',
            'text-amber-700 dark:text-amber-300' => $paidStatus === 'partial',
            'text-gray-700 dark:text-gray-200' => $paidStatus === 'none',
        ])>
            @if ($paidStatus === 'full')
                <span aria-hidden="true">✓</span>
            @endif
            {{ $paid }}
        </div>
        @if ($paymentHint)
            <div class="mt-0.5 text-[11px] font-normal text-sky-700 dark:text-sky-300" title="{{ $paymentHint }}">
                {{ $paymentHint }}
            </div>
        @endif
        @if (! empty($pilotDueHint))
            <div class="mt-0.5 text-[11px] font-medium text-blue-700 dark:text-blue-300" title="{{ $pilotDueHint }}">
                {{ $pilotDueHint }}
            </div>
        @elseif (! empty($remainingHint) && $paidStatus !== 'full')
            <div class="mt-0.5 text-[11px] font-normal text-rose-700 dark:text-rose-300">
                {{ $remainingHint }}
            </div>
        @endif
        @if ($statusLabel)
            <div class="mt-1 flex justify-end">
                <span @class([
                    'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                    match ($statusColor ?? 'gray') {
                        'success' => 'bg-emerald-100 text-emerald-800',
                        'warning' => 'bg-amber-100 text-amber-800',
                        'danger' => 'bg-rose-100 text-rose-800',
                        default => 'bg-gray-100 text-gray-700',
                    },
                ])>
                    {{ $statusLabel }}
                </span>
            </div>
        @endif
        @if (! empty($documentHint))
            <div
                @class([
                    'mt-1 truncate text-[10px] font-medium',
                    'text-emerald-700' => $hasUploadedFile,
                    'text-amber-700' => ! $hasUploadedFile,
                ])
                title="{{ $documentHint }}"
            >
                {{ $hasUploadedFile ? 'Plik' : 'Dok.' }}: {{ $documentHint }}
            </div>
        @endif
    @endif
</div>
