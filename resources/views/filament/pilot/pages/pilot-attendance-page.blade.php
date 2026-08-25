<x-filament-panels::page>
    @include('filament.client.components.trip-hero', [
        'event' => $this->event,
        'kicker' => 'Obecność',
    ])

    @if(filled($archiveMessage))
        <div class="portal-notice portal-notice--amber">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="portal-card mb-3">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs text-[#888780]">Dzień wycieczki</label>
                <select wire:model.live="day" class="fi-input rounded-lg border-[#D3D1C7] text-sm" @disabled($readOnly)>
                    @for($d = 1; $d <= $this->maxDay; $d++)
                        <option value="{{ $d }}">Dzień {{ $d }}</option>
                    @endfor
                </select>
            </div>
            @unless($readOnly)
                <button type="button" wire:click="save" class="portal-btn-primary">
                    Zapisz obecność
                </button>
            @endunless
        </div>
    </div>

    @if ($this->participants === [])
        <div class="portal-card">
            <p class="portal-muted" style="margin:0;">Brak uczestników na liście. Biuro musi uzupełnić listę uczestników.</p>
        </div>
    @else
        <div class="portal-card !p-0 overflow-hidden">
            <div class="portal-card-title px-4 pt-4 pb-2">
                <p>Lista obecności</p>
            </div>
            <ul class="divide-y divide-[#E5E3DA]">
                @foreach ($this->participants as $participant)
                    <li class="portal-attendance-row" wire:key="attendance-{{ $participant->id }}-{{ $day }}">
                        <label class="portal-attendance-check">
                            <input
                                type="checkbox"
                                wire:model.live="statuses.{{ $participant->id }}"
                                @disabled($readOnly)
                                aria-label="Obecny: {{ $participant->fullName() }}"
                            />
                            <span class="text-sm font-medium text-[#2C2C2A]">{{ $participant->fullName() }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-filament-panels::page>
