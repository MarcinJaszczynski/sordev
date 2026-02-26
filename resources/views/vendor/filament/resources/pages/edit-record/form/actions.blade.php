@php
    $actions = $this->getCachedFormActions();

    $actionsAlignment = $this->getActionsAlignment();
    $actionsPosition = $this->getActionsPosition();

    $hasCustomActions = true;

    $record = $getRecord();
@endphp

<div x-data="{ showProgramModal: false }">
    <button type="button" class="fi-btn fi-btn-color-primary mb-4" @click="showProgramModal = true">
        Pokaż program imprezy
    </button>
    <div x-show="showProgramModal" style="display: none"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg max-w-4xl w-full p-6 relative">
            <button type="button" class="absolute top-2 right-2 text-gray-500 hover:text-gray-900"
                @click="showProgramModal = false">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h2 class="text-xl font-bold mb-4">Program imprezy</h2>
            @include('filament.resources.event-template-resource.widgets.program-table', ['record' => $record])
        </div>
    </div>
</div>

<div x-data="{ showProgramModal: false }" class="mb-4">
    <button type="button" class="fi-btn fi-btn-color-primary" @click="showProgramModal = true">
        Pokaż program imprezy
    </button>
    <div x-show="showProgramModal" style="display: none"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg max-w-4xl w-full p-6 relative">
            <button type="button" class="absolute top-2 right-2 text-gray-500 hover:text-gray-900"
                @click="showProgramModal = false">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h2 class="text-xl font-bold mb-4">Program imprezy</h2>
            @include('filament.resources.event-template-resource.widgets.program-table', ['record' => $record])
        </div>
    </div>
</div>

<div @class([
    'fi-form-actions',
    'mt-6' => $actionsPosition === 'below',
    match ($actionsAlignment) {
        'center' => 'text-center',
        'left' => 'text-start',
        'right' => 'text-end',
        default => match ($actionsPosition) {
                'above' => 'sm:text-end',
                default => 'sm:text-end',
            },
    },
])>
    @if ($actions)
        <x-filament-actions::actions :actions="$actions" :alignment="$actionsAlignment"
            :full-width="$this->hasFullWidthFormActions()" :is-spaced="true" />
    @endif
</div>