@props([
    'notableType',
    'notableId',
    'title' => 'Karteczki',
    'compact' => false,
    'filterCategory' => null,
])

@livewire('sticky-notes-stack', [
    'notableType' => $notableType,
    'notableId' => $notableId,
    'title' => $title,
    'compact' => $compact,
    'filterCategory' => $filterCategory,
], key('sticky-notes-'.md5($notableType.'-'.$notableId.'-'.($filterCategory ?? 'all'))))
