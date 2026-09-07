<x-filament-panels::page>
    @php
        $c = $this->completeness;
        $diet = $this->dietCatalog;
        $dietOptions = $this->dietSelectOptions();
    @endphp

    @include('filament.client.components.trip-hero', [
        'event' => $this->event->loadMissing(['eventTemplate', 'startPlace']),
        'kicker' => 'Uczestnicy',
    ])

    @if($diet['enabled'])
        <div class="portal-notice portal-notice--accent">
            Dopłata diety specjalnej:
            <strong>{{ number_format($diet['daily_pln'], 2, ',', ' ') }} PLN/dzień</strong>
            × {{ $diet['days'] }} {{ $diet['days'] === 1 ? 'dzień' : 'dni' }}
            = <strong>{{ number_format($diet['per_person_pln'], 2, ',', ' ') }} PLN</strong> na osobę.
            @if($diet['surcharge_pln'] > 0)
                Aktualnie doliczone: {{ number_format($diet['surcharge_pln'], 2, ',', ' ') }} PLN.
            @endif
        </div>
    @endif

    <div class="mb-4 client-portal-section">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-[#2C2C2A]">Kompletność listy: {{ $c['completeness_pct'] }}%</p>
                <p class="text-xs text-[#888780]">
                    {{ $c['with_name'] }}/{{ $c['total'] }} z imieniem ·
                    {{ $c['with_consent'] }}/{{ $c['total'] }} ze zgodami ·
                    zaległości: {{ $c['overdue'] }}
                    ({{ number_format($c['overdue_total_pln'], 2, ',', ' ') }} PLN)
                </p>
            </div>
            <x-filament::button wire:click="exportCsv" color="gray" icon="heroicon-o-arrow-down-tray">
                Eksport CSV
            </x-filament::button>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-[#F1EFE8]">
            <div class="h-full rounded-full bg-[#2C2C2A]" style="width: {{ max(0, min(100, $c['completeness_pct'])) }}%"></div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="client-portal-section overflow-hidden !p-0">
            <header class="border-b border-[#E5E3DA] bg-[#F1EFE8] px-4 py-3">
                <h3 class="text-sm font-semibold text-[#2C2C2A]">Lista uczestników</h3>
            </header>
            @if ($this->participants === [])
                <p class="px-4 py-4 text-sm text-[#888780]">Brak uczestników. Dodaj pierwszą osobę w formularzu obok.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-[#F1EFE8] text-left text-xs uppercase text-[#888780]">
                            <tr>
                                <th class="px-4 py-2">Imię i nazwisko</th>
                                <th class="px-4 py-2">Płeć</th>
                                <th class="px-4 py-2">Dieta</th>
                                <th class="px-4 py-2">Zgody</th>
                                <th class="px-4 py-2">Wpłaty</th>
                                <th class="px-4 py-2">Umowa</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->participants as $participant)
                                @php
                                    $status = $this->statusFor($participant);
                                    $due = (float) ($status['due_pln'] ?? 0);
                                    $remaining = (float) ($status['remaining_pln'] ?? 0);
                                    $tone = static fn (string $t): string => match ($t) {
                                        'emerald' => 'text-emerald-700',
                                        'rose' => 'text-rose-700',
                                        'amber' => 'text-amber-700',
                                        default => 'text-[#5F5E5A]',
                                    };
                                    $hasDiet = filled($participant->diet);
                                @endphp
                                <tr wire:key="client-participant-{{ $participant->id }}">
                                    <td class="px-4 py-2 font-medium">{{ $participant->fullName() ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $participant->genderLabel() ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        {{ $participant->diet ?: '—' }}
                                        @if($hasDiet && $diet['enabled'] && $diet['per_person_pln'] > 0)
                                            <div class="text-[11px] font-medium text-[#0C447C]">
                                                +{{ number_format($diet['per_person_pln'], 2, ',', ' ') }} PLN
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($participant->hasParentConsent())
                                            <span class="text-emerald-700">{{ $participant->consentsCompletedLabel() }}</span>
                                        @else
                                            <span class="text-amber-700">{{ $participant->consentsCompletedLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="{{ $tone($status['payment_tone']) }} text-xs font-medium">{{ $status['payment_label'] }}</div>
                                        @if ($due > 0)
                                            <div class="text-[11px] text-[#888780]">
                                                pozostało {{ number_format($remaining, 2, ',', ' ') }} PLN
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="{{ $tone($status['contract_tone']) }} text-xs font-medium">{{ $status['contract_label'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button type="button" class="text-[#0C447C] hover:underline" wire:click="startEdit({{ $participant->id }})">Edytuj</button>
                                        <button type="button" class="ml-2 text-[#0C447C] hover:underline" wire:click="copyParentLink({{ $participant->id }})">Link rodzica</button>
                                        <button type="button" class="ml-2 text-rose-600 hover:underline" wire:click="delete({{ $participant->id }})" wire:confirm="Usunąć uczestnika?">Usuń</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="client-portal-section space-y-3">
            <h3 class="text-sm font-semibold text-[#2C2C2A]">{{ $editId ? 'Edycja' : 'Nowy uczestnik' }}</h3>
            <div>
                <label class="mb-1 block text-xs text-[#5F5E5A]">Imię</label>
                <input type="text" wire:model="formFirstName" class="fi-input w-full rounded-lg border-slate-300 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#5F5E5A]">Nazwisko</label>
                <input type="text" wire:model="formLastName" class="fi-input w-full rounded-lg border-slate-300 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#5F5E5A]">Płeć</label>
                <select wire:model="formGender" class="fi-input w-full rounded-lg border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach (\App\Models\EventParticipant::$genders as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-[#5F5E5A]">Dieta</label>
                @if($diet['enabled'] && count($diet['options']) > 0)
                    <select wire:model="formDiet" class="fi-input w-full rounded-lg border-slate-300 text-sm">
                        @foreach($dietOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" wire:model="formDiet" class="fi-input w-full rounded-lg border-slate-300 text-sm" placeholder="{{ $diet['enabled'] ? 'np. bezglutenowa (+dopłata)' : 'np. bezglutenowa' }}" />
                @endif
                @if($diet['enabled'] && $diet['per_person_pln'] > 0)
                    <p class="mt-1 text-[11px] text-[#888780]">
                        Wybór diety dolicza {{ number_format($diet['per_person_pln'], 2, ',', ' ') }} PLN do umowy.
                    </p>
                @endif
            </div>
            <div class="space-y-2 rounded-lg border border-[#E5E3DA] bg-[#F1EFE8] p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#888780]">Zgody</p>
                @foreach ($this->consentLabels() as $key => $label)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" wire:model="formConsents.{{ $key }}" class="mt-0.5 rounded border-slate-300" />
                        <span>
                            {{ $label }}
                            @if (in_array($key, $this->requiredConsentKeys(), true))
                                <span class="text-xs text-amber-700">(wymagane)</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="flex gap-2">
                <x-filament::button wire:click="save" color="primary" class="flex-1">
                    {{ $editId ? 'Zapisz' : 'Dodaj' }}
                </x-filament::button>
                @if ($editId)
                    <x-filament::button wire:click="cancelEdit" color="gray">Anuluj</x-filament::button>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>

@script
<script>
    $wire.on('copy-to-clipboard', ({ url }) => {
        if (navigator.clipboard && url) {
            navigator.clipboard.writeText(url).catch(() => {});
        }
    });
</script>
@endscript
