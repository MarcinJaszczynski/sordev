@php
    $story = $this->cashResourceStory;
    $compact = $compact ?? false;
    $canEditExchange = $canEditExchange ?? false;
@endphp

@if ($story->has_movements)
    <div @class([
        'mb-3 rounded-xl border px-3 py-3',
        'border-teal-200 bg-teal-50/80 dark:border-teal-900/50 dark:bg-teal-950/30' => ! $compact,
        'border-teal-200 bg-teal-50/80' => $compact,
    ])>
        <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-800 dark:text-teal-200">
            Stan zasobów pilota
        </p>
        <p class="mt-1 text-sm font-semibold tabular-nums text-teal-950 dark:text-teal-50">
            {{ $story->summary_label }}
        </p>

        @if ($story->chain_lines !== [])
            <p class="mt-1.5 text-xs leading-relaxed text-teal-900/80 dark:text-teal-100/80">
                Skąd:
                {{ implode(' → ', $story->chain_lines) }}
            </p>
        @endif

        @if ($story->ledger->isNotEmpty())
            <ul class="mt-3 space-y-1.5 border-t border-teal-200/80 pt-2 dark:border-teal-800/60">
                @foreach ($story->ledger as $entry)
                    <li class="flex flex-wrap items-start justify-between gap-2 text-sm" wire:key="cash-ledger-{{ $entry->id }}">
                        <div class="min-w-0">
                            <span class="font-medium text-teal-950 dark:text-teal-50">
                                {{ $entry->title }}:
                                <span class="tabular-nums">{{ $entry->label }}</span>
                            </span>
                            @if ($entry->detail)
                                <span class="block text-[11px] text-teal-800/80 dark:text-teal-200/80">{{ $entry->detail }}</span>
                            @endif
                        </div>
                        <span class="flex shrink-0 items-center gap-2 text-[11px] text-teal-800/70 dark:text-teal-200/70">
                            <span>{{ $entry->at?->format('d.m.Y') ?: '—' }}</span>
                            @if ($canEditExchange && $entry->type === 'exchange' && $entry->exchange_id)
                                <button
                                    type="button"
                                    wire:click="editExchange({{ $entry->exchange_id }})"
                                    class="font-medium text-indigo-700 hover:underline dark:text-indigo-300"
                                >Edytuj</button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
