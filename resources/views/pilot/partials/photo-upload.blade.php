@props([
    'wireModel',
    'multiple' => false,
    'compact' => false,
])

@php
    $uid = 'pu-'.md5($wireModel);
    $btnClass = $compact
        ? 'pilot-touch-btn flex-1 min-w-[8rem] border border-gray-300 bg-white text-gray-900 text-sm text-center cursor-pointer inline-flex items-center justify-center gap-1.5'
        : 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50';
    $cameraTarget = $wireModel.',uploadCostDocument,uploadDocument';
@endphp

<div {{ $attributes->merge(['class' => 'pilot-photo-upload space-y-2']) }}>
    <div class="flex flex-wrap gap-2">
        <label for="{{ $uid }}-camera" class="{{ $btnClass }}">
            <svg class="h-5 w-5 shrink-0 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
            Zrób zdjęcie
        </label>
        <input
            id="{{ $uid }}-camera"
            type="file"
            accept="image/*"
            capture="environment"
            class="sr-only"
            wire:model.live.debounce.500ms="{{ $wireModel }}"
            @if($multiple) multiple @endif
        />

        <label for="{{ $uid }}-file" class="{{ $btnClass }}">
            <svg class="h-5 w-5 shrink-0 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            {{ $multiple ? 'Wybierz pliki' : 'Wybierz plik' }}
        </label>
        <input
            id="{{ $uid }}-file"
            type="file"
            accept="image/*,application/pdf"
            class="sr-only"
            wire:model.live.debounce.500ms="{{ $wireModel }}"
            @if($multiple) multiple @endif
        />
    </div>

    <div wire:loading wire:target="{{ $cameraTarget }}" class="text-xs text-gray-500">
        Przesyłanie zdjęcia…
    </div>

    @error($wireModel)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error($wireModel.'.*')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
