@if ($assigningLineId)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4"
        wire:key="bank-assign-modal-{{ $assigningLineId }}"
    >
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Przypisz wpłatę bankową</h3>
                    <p class="mt-1 text-sm text-gray-500">Wskaż cel księgowania lub zmień istniejące dopasowanie.</p>
                </div>
                <button type="button" class="text-sm text-gray-500 hover:text-gray-800" wire:click="closeAssignModal">Zamknij</button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Typ przypisania</label>
                    <select wire:model.live="assignTargetType" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option value="participant_payment">Uczestnik (wpłata)</option>
                        <option value="contract">Umowa</option>
                        <option value="pilot_advance">Zaliczka pilota</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Impreza</label>
                    <select wire:model.live="assignEventId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <option value="">— wybierz —</option>
                        @foreach ($this->assignEventOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($assignTargetType === 'participant_payment')
                    <div>
                        <label class="mb-1 block text-sm font-medium">Uczestnik</label>
                        <select wire:model="assignParticipantPaymentId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="">— wybierz —</option>
                            @foreach ($this->assignParticipantPaymentOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($assignTargetType === 'contract')
                    <div>
                        <label class="mb-1 block text-sm font-medium">Umowa</label>
                        <select wire:model="assignContractId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="">— wybierz —</option>
                            @foreach ($this->assignContractOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="assignAndApply" class="rounded border-gray-300" />
                    Zaksięguj od razu po przypisaniu
                </label>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <button
                    type="button"
                    class="text-sm text-danger-600 hover:underline"
                    wire:click="clearAssignment({{ $assigningLineId }})"
                >
                    Wyczyść przypisanie
                </button>
                <div class="flex gap-2">
                    <x-filament::button color="gray" wire:click="closeAssignModal">Anuluj</x-filament::button>
                    <x-filament::button color="primary" wire:click="saveAssignment">Zapisz</x-filament::button>
                </div>
            </div>
        </div>
    </div>
@endif
