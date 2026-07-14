<x-filament-panels::page>
    @if(filled($archiveMessage))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $archiveMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="sor-lw-card">
            <h3 class="sor-lw-title mb-4">Nowy wniosek o fakturę</h3>
            <form wire:submit="submit" class="space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit" class="mt-2">
                    Wyślij wniosek
                </x-filament::button>
            </form>
        </section>

        <section class="sor-lw-card overflow-hidden !p-0">
            <header class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <h3 class="sor-lw-title">Historia wniosków</h3>
            </header>
            @if($requests->isEmpty())
                <p class="px-4 py-3 text-sm text-gray-500">Brak wcześniejszych wniosków.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($requests as $request)
                        <li class="px-4 py-3 text-sm">
                            <div class="font-medium">{{ $request->company_name }}</div>
                            <div class="text-gray-500">NIP: {{ $request->nip }} · {{ $request->created_at->format('d.m.Y H:i') }}</div>
                            <div class="mt-1">Status: {{ $request->status_label }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>
