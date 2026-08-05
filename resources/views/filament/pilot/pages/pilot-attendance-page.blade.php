<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs text-gray-600">Dzień wycieczki</label>
            <select wire:model.live="day" class="fi-input rounded-lg border-gray-300 text-sm" @disabled($readOnly)>
                @for($d = 1; $d <= $this->maxDay; $d++)
                    <option value="{{ $d }}">Dzień {{ $d }}</option>
                @endfor
            </select>
        </div>
        @unless($readOnly)
            <x-filament::button wire:click="save" color="primary" icon="heroicon-o-check">
                Zapisz obecność
            </x-filament::button>
        @endunless
    </div>

    @if ($this->participants === [])
        <div class="sor-lw-card text-sm text-gray-600">
            Brak uczestników na liście. Biuro musi uzupełnić listę uczestników.
        </div>
    @else
        <div class="sor-lw-card overflow-hidden !p-0">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Uczestnik</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->participants as $participant)
                        <tr wire:key="attendance-{{ $participant->id }}-{{ $day }}">
                            <td class="px-4 py-2 font-medium">{{ $participant->fullName() }}</td>
                            <td class="px-4 py-2">
                                <select
                                    wire:model="statuses.{{ $participant->id }}"
                                    class="fi-input rounded-lg border-gray-300 text-sm"
                                    @disabled($readOnly)
                                >
                                    <option value="present">Obecny</option>
                                    <option value="absent">Nieobecny</option>
                                    <option value="unknown">—</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
