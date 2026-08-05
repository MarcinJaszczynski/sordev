<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <section class="sor-lw-card overflow-hidden !p-0">
            <header class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <h3 class="sor-lw-title">Lista uczestników</h3>
            </header>
            @if ($this->participants === [])
                <p class="px-4 py-4 text-sm text-gray-500">Brak uczestników. Dodaj pierwszą osobę w formularzu obok.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Imię i nazwisko</th>
                                <th class="px-4 py-2">Dieta</th>
                                <th class="px-4 py-2">Zgoda</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($this->participants as $participant)
                                <tr wire:key="client-participant-{{ $participant->id }}">
                                    <td class="px-4 py-2 font-medium">{{ $participant->fullName() ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $participant->diet ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($participant->hasParentConsent())
                                            <span class="text-emerald-700">Tak</span>
                                        @else
                                            <span class="text-amber-700">Brak</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button type="button" class="text-primary-600 hover:underline" wire:click="startEdit({{ $participant->id }})">Edytuj</button>
                                        <button type="button" class="ml-2 text-danger-600 hover:underline" wire:click="delete({{ $participant->id }})" wire:confirm="Usunąć uczestnika?">Usuń</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="sor-lw-card space-y-3">
            <h3 class="sor-lw-title">{{ $editId ? 'Edycja' : 'Nowy uczestnik' }}</h3>
            <div>
                <label class="mb-1 block text-xs text-gray-600">Imię</label>
                <input type="text" wire:model="formFirstName" class="fi-input w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-600">Nazwisko</label>
                <input type="text" wire:model="formLastName" class="fi-input w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-600">Dieta</label>
                <input type="text" wire:model="formDiet" class="fi-input w-full rounded-lg border-gray-300 text-sm" placeholder="np. bezglutenowa" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="formParentConsent" class="rounded border-gray-300" />
                Zgoda rodzica / opiekuna
            </label>
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
