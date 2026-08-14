@php
    /** @var \App\Models\EventProgramPoint|null $record */
    $record = $getRecord();
@endphp

@if ($record)
    @php
        $isChild = filled($record->parent_id);
        $isSetParent = (bool) $record->getAttribute('_is_set_parent');
        $isSetExpanded = (bool) $record->getAttribute('_set_expanded');
        $childCount = (int) ($record->children_count ?? 0);
        $order = sprintf('%02d', (int) ($record->order ?? 1)); // used in set preview
        $name = $record->name ?? $record->templatePoint?->name ?? '—';

        $start = $record->hide_times ? null : ($record->start_time ? substr((string) $record->start_time, 0, 5) : null);
        $end = $record->hide_times ? null : ($record->end_time ? substr((string) $record->end_time, 0, 5) : null);

        $contractorName = $record->contractor?->name;
        $reservation = $record->relationLoaded('reservations')
            ? $record->reservations->sortByDesc('id')->first()
            : $record->reservations()->withTrashed()->orderByDesc('id')->first();
        $reservationIsTrashed = $reservation?->trashed() ?? false;

        /** @var \Illuminate\Support\Collection<int, \App\Models\EventProgramPoint>|null $setChildren */
        $setChildren = $record->getAttribute('_set_children_preview');
        $visibleChildIds = collect($record->getAttribute('_set_children_visible_ids') ?? []);
        $hiddenChildCount = $setChildren instanceof \Illuminate\Support\Collection
            ? $setChildren->filter(fn (\App\Models\EventProgramPoint $child): bool => ! $visibleChildIds->contains((int) $child->id))->count()
            : 0;
    @endphp

    <div @class([
        'epp-name-cell',
        'epp-name-cell--child' => $isChild,
        'epp-name-cell--set-parent' => $isSetParent,
        'epp-name-cell--has-set-preview' => $isSetParent && $setChildren instanceof \Illuminate\Support\Collection && $setChildren->isNotEmpty(),
    ])>
        @if ($isChild)
            <span class="epp-tree-branch" aria-hidden="true"></span>
        @endif

        <div class="epp-name-head">
            @if ($isSetParent)
                <span class="epp-set-badge-wrap">
                    <button
                        type="button"
                        @class([
                            'epp-set-badge',
                            'epp-set-badge--expanded' => $isSetExpanded,
                        ])
                        aria-expanded="{{ $isSetExpanded ? 'true' : 'false' }}"
                        aria-label="{{ $isSetExpanded ? 'Zwiń set' : 'Rozwiń set' }} — {{ $childCount }} {{ $childCount === 1 ? 'podpunkt' : 'podpunkty' }}"
                        x-on:click.stop="$wire.toggleSetExpanded({{ $record->id }})"
                    >
                        <span class="epp-set-badge__chevron" aria-hidden="true"></span>
                        <span class="epp-set-badge__icon" aria-hidden="true"></span>
                        <span class="epp-set-badge__label">Set</span>
                        <span class="epp-set-badge__count">{{ $childCount }} {{ $childCount === 1 ? 'podpunkt' : 'podpunkty' }}</span>
                    </button>
                </span>
            @endif

            @if ($start && $end)
                <span class="epp-time">{{ $start }}–{{ $end }}</span>
            @else
                <span class="epp-time epp-time--muted">bez godzin</span>
            @endif

            <span class="epp-type-icons">
                @if ($record->is_transport)
                    <span class="epp-type-icon" title="Transport">🚌</span>
                @endif
                @if ($record->is_hotel)
                    <span class="epp-type-icon" title="Nocleg / Hotel">🏨</span>
                @endif
                @if ($record->is_hotel_service)
                    <span class="epp-type-icon" title="Usługa hotelu">🍽</span>
                @endif
                @php
                    $gallery = is_array($record->gallery_images) ? $record->gallery_images : [];
                    $hasAttachments = filled($record->featured_image) || count($gallery) > 0;
                    $attachmentCount = (filled($record->featured_image) ? 1 : 0) + count($gallery);
                @endphp
                @if ($hasAttachments)
                    <span class="epp-type-icon" title="Załączniki / zdjęcia: {{ $attachmentCount }}">📎 {{ $attachmentCount }}</span>
                @endif
            </span>
        </div>

        <div class="epp-title">{{ $name }}</div>

        @php
            // W panelu admin zawsze pokazujemy opis (show_description steruje tylko frontem / PDF).
            $descHtml = trim((string) ($record->description ?? $record->templatePoint?->description ?? ''));
            $descPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($descHtml)) ?? '');
        @endphp
        @if ($descPlain !== '')
            <div class="epp-program-desc" title="{{ $descPlain }}">
                {{ \Illuminate\Support\Str::limit($descPlain, 90) }}
            </div>
        @endif

        @if ($contractorName || $reservation)
            <div class="epp-meta">
                @if ($contractorName)
                    @php
                        $contractor = $record->contractor;
                    @endphp
                    <div>
                        <span class="font-medium">{{ $contractorName }}</span>
                        @if ($contractor)
                            <x-contractor-contact-details
                                :contractor="$contractor"
                                :location="$record->contractorLocation"
                                class="mt-0.5 text-[11px] leading-snug text-gray-600 dark:text-gray-400"
                            />
                        @endif
                    </div>
                @endif
                @if ($reservation)
                    @if ($contractorName)
                        <span class="epp-meta-sep">|</span>
                    @endif
                    @php
                        $resStatus = \App\Models\Reservation::$statuses[$reservation->status] ?? $reservation->status;
                        $depositLabel = \App\Support\Reservations\ReservationWorkflowDisplay::depositStatusLabel($reservation);
                        $confirmBy = filled($reservation->confirm_by)
                            ? \App\Support\Reservations\ReservationWorkflowDisplay::formatDate($reservation->confirm_by)
                            : null;
                    @endphp
                    <span class="inline-flex flex-wrap items-center gap-1">
                        @if ($reservationIsTrashed)
                            <span class="epp-reservation-badge epp-reservation-badge--cancelled" title="Rezerwacja usunięta z punktu">
                                Rezerwacja usunięta
                            </span>
                        @endif
                        <span class="epp-reservation-badge epp-reservation-badge--{{ $reservation->status }}">
                            {{ $resStatus }}
                        </span>
                        <span class="epp-reservation-badge epp-reservation-badge--deposit-{{ \App\Support\Reservations\ReservationWorkflowDisplay::depositStatus($reservation) }}">
                            {{ $depositLabel }}
                        </span>
                        @if ($confirmBy)
                            <span class="text-[11px] text-gray-500">potw. do {{ $confirmBy }}</span>
                        @endif
                        @if (filled($reservation->booking_reference))
                            <span class="text-[11px] text-gray-500">nr {{ $reservation->booking_reference }}</span>
                        @endif
                    </span>
                @endif
            </div>
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-1">
            <button
                type="button"
                wire:click="openCreateTaskForProgramPoint({{ $record->getKey() }})"
                class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 transition hover:bg-slate-50"
                x-on:click.stop
            >
                + Zadanie
            </button>
        </div>

        @if ($isSetParent && ! $isSetExpanded && $setChildren instanceof \Illuminate\Support\Collection && $setChildren->isNotEmpty())
            <div class="epp-set-preview" role="tooltip" aria-hidden="true">
                <div class="epp-set-preview__head">
                    Podpunkty setu
                    <span class="epp-set-preview__count">{{ $setChildren->count() }}</span>
                </div>

                <div class="epp-set-preview__list">
                    @foreach ($setChildren->sortBy(fn (\App\Models\EventProgramPoint $child): int => (int) ($child->order ?? 0)) as $child)
                        @php
                            $childName = $child->name ?? $child->templatePoint?->name ?? '—';
                            $childOrder = sprintf('%02d', (int) ($child->order ?? 1));
                            $inView = $visibleChildIds->contains((int) $child->id);
                            $flags = [];
                            if (! $child->include_in_program) {
                                $flags[] = 'poza programem';
                            }
                            if (! $child->include_in_calculation) {
                                $flags[] = 'poza kalk.';
                            }
                            if (! $child->active) {
                                $flags[] = 'nieaktywny';
                            }
                            if ($child->trashed()) {
                                $flags[] = 'usunięty';
                            }
                        @endphp

                        <button
                            type="button"
                            @class([
                                'epp-set-preview__item',
                                'epp-set-preview__item--visible' => $inView,
                                'epp-set-preview__item--hidden' => ! $inView,
                            ])
                            @if ($inView)
                                x-on:click.stop="$wire.openSetChild({{ $child->id }})"
                            @else
                                x-on:click.stop="$wire.revealSetChildInTable({{ $child->id }})"
                            @endif
                        >
                            <span class="epp-set-preview__item-main">
                                <span class="epp-set-preview__order">{{ $childOrder }}</span>
                                <span class="epp-set-preview__name">{{ $childName }}</span>
                            </span>

                            @if ($flags !== [])
                                <span class="epp-set-preview__flags">{{ implode(' · ', $flags) }}</span>
                            @else
                                <span class="epp-set-preview__flags epp-set-preview__flags--ok">w programie i kalkulacji</span>
                            @endif

                            <span class="epp-set-preview__action">{{ $inView ? 'Edytuj' : 'Pokaż i edytuj' }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="epp-set-preview__foot">
                    @if ($hiddenChildCount > 0)
                        {{ $hiddenChildCount }} {{ $hiddenChildCount === 1 ? 'podpunkt ukryty' : 'podpunkty ukryte' }} — kliknij, aby rozwinąć set i edytować.
                    @else
                        Kliknij „Set”, aby rozwinąć podpunkty w tabeli, lub wybierz podpunkt z listy.
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
