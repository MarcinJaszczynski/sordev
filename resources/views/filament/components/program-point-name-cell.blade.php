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
        $name = $record->name ?? $record->templatePoint?->name ?? '—';
        $inProgram = (bool) $record->include_in_program;
        $inCalc = (bool) $record->include_in_calculation;

        // W panelu admin zawsze pokazujemy opis (show_description steruje tylko frontem / PDF).
        // resolvedDescription: null = dziedzicz z szablonu, '' = celowo pusty.
        $descHtml = trim((string) ($record->resolvedDescription() ?? ''));
        $descPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($descHtml)) ?? '');

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
        @php
            $gallery = is_array($record->gallery_images) ? $record->gallery_images : [];
            $hasAttachments = filled($record->featured_image) || count($gallery) > 0;
            $attachmentCount = (filled($record->featured_image) ? 1 : 0) + count($gallery);
            $hasTypeIcons = $record->is_transport || $record->is_hotel || $record->is_hotel_service || $hasAttachments;
            $showNameHead = $isSetParent || $hasTypeIcons;
        @endphp

        @php
            $timeStart = null;
            $timeEnd = null;
            if (! $record->hide_times) {
                $timeStart = $record->start_time ? substr((string) $record->start_time, 0, 5) : null;
                $timeEnd = $record->end_time ? substr((string) $record->end_time, 0, 5) : null;
            }
            $hasTime = filled($timeStart);
        @endphp

        @if ($hasTime)
            <div class="epp-name-time" aria-label="Godzina">
                <span class="epp-name-time__start">{{ $timeStart }}</span>
                @if (filled($timeEnd))
                    <span class="epp-name-time__sep">–</span>
                    <span class="epp-name-time__end">{{ $timeEnd }}</span>
                @endif
            </div>
        @endif

        @if ($showNameHead)
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
                            <span class="epp-set-badge__count">{{ $childCount }}</span>
                        </button>
                    </span>
                @endif

                @if ($hasTypeIcons)
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
                        @if ($hasAttachments)
                            <span class="epp-type-icon" title="Załączniki / zdjęcia: {{ $attachmentCount }}">📎 {{ $attachmentCount }}</span>
                        @endif
                    </span>
                @endif
            </div>
        @endif

        <div class="epp-title-row">
            <span class="epp-title">{{ $name }}</span>
            @if ($inProgram || $inCalc)
                <span class="epp-scope-pills" title="Program = oferta/PDF. Koszt = lista kosztów i rozliczenie.">
                    @if ($inProgram)
                        <span class="epp-mini-pill epp-mini-pill--program epp-mini-pill--on">program</span>
                    @endif
                    @if ($inCalc)
                        <span class="epp-mini-pill epp-mini-pill--cost epp-mini-pill--on">koszt</span>
                    @endif
                </span>
            @endif
            <button
                type="button"
                wire:click="openCreateTaskForProgramPoint({{ $record->getKey() }})"
                class="epp-task-btn"
                x-on:click.stop
            >
                + Zadanie
            </button>
        </div>

        @if ($descPlain !== '')
            <div class="epp-point-sub" title="{{ $descPlain }}">
                {{ $descPlain }}
            </div>
        @endif

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
                                <span class="epp-set-preview__flags epp-set-preview__flags--ok">w programie i w kosztach</span>
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
