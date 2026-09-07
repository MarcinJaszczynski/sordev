@props([
    'point',
    'selected' => false,
    'isChild' => false,
    'isSetParent' => false,
    'childCount' => 0,
    'dragClass' => 'drag-handle',
])

@php
    $name = $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id);
    $start = $point->hide_times ? null : ($point->start_time ? substr((string) $point->start_time, 0, 5) : null);
    $end = $point->hide_times ? null : ($point->end_time ? substr((string) $point->end_time, 0, 5) : null);
    $timeLabel = ($start && $end) ? "{$start}–{$end}" : ($point->hide_times ? '—' : '');
@endphp

<div @class(['epp-tr', 'epp-tr--child' => $isChild, 'epp-tr--inactive' => ! $point->active, 'epp-tr--selected' => $selected])>
    <div class="epp-td epp-td--check">
        <input type="checkbox" @checked($selected) wire:click="toggleSelectPoint({{ $point->id }})" aria-label="Zaznacz">
    </div>
    <div class="epp-td epp-td--drag">
        <span class="epp-tree-drag {{ $dragClass }}" title="Przeciągnij">⋮⋮</span>
    </div>
    <div class="epp-td epp-td--order">
        @if($isChild)
            <span class="epp-order epp-order--child">{{ sprintf('%02d', (int) ($point->order ?? 1)) }}</span>
        @else
            <span class="epp-order">{{ sprintf('%02d', (int) ($point->order ?? 1)) }}</span>
        @endif
    </div>
    <div class="epp-td epp-td--time">
        @if($timeLabel !== '')
            <span @class(['epp-time', 'epp-time--muted' => $point->hide_times && $start === null])>{{ $timeLabel }}</span>
        @else
            <span class="epp-time epp-time--empty">—</span>
        @endif
    </div>
    <div class="epp-td epp-td--name">
        @if($isSetParent)
            <span class="epp-set-chip">Set · {{ $childCount }}</span>
        @endif
        <span class="epp-name">{{ $name }}</span>
    </div>
    <div class="epp-td epp-td--flags">
        @include('livewire.partials.event-program-point-flags', ['point' => $point])
    </div>
    <div class="epp-td epp-td--actions">
        <details class="epp-menu">
            <summary class="epp-menu__trigger" title="Akcje">⋯</summary>
            <div class="epp-menu__panel">
                <button type="button" wire:click="openEdit({{ $point->id }})">Edytuj</button>
                @if(! $isChild)
                    <button type="button" wire:click="openAddChild({{ $point->id }})">Dodaj podpunkt</button>
                    <button type="button" wire:click="duplicatePoint({{ $point->id }})">Duplikuj</button>
                    <button type="button" wire:click="openCreateTaskForPoint({{ $point->id }})">Nowe zadanie</button>
                @else
                    <button type="button" wire:click="detachFromSet({{ $point->id }})" wire:confirm="Odpiąć od setu?">Odepnij od setu</button>
                @endif
                <button
                    type="button"
                    class="epp-menu__danger"
                    wire:click="deletePoint({{ $point->id }})"
                    wire:confirm="{{ $isSetParent
                        ? 'Usunąć cały set „'.e($name).'” wraz z '.$childCount.' podpunktami? Po usunięciu możesz to cofnąć w liście programu.'
                        : ($isChild
                            ? 'Usunąć podpunkt „'.e($name).'” z setu?'
                            : 'Usunąć punkt „'.e($name).'”?') }}"
                >Usuń</button>
            </div>
        </details>
    </div>
</div>
