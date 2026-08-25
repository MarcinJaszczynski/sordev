<x-filament-panels::page>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="client-portal-kicker">Witaj</p>
            <h2 class="mt-1 text-xl font-semibold text-[#2C2C2A]">Twoje wycieczki</h2>
            <p class="mt-1 text-sm text-[#5F5E5A]">Szybki dostęp do programu, umowy i płatności.</p>
        </div>
        <a href="{{ $allTripsUrl }}" class="text-sm font-semibold text-[#0C447C] hover:underline">
            Wszystkie wycieczki →
        </a>
    </div>

    @include('filament.client.components.trip-cards', ['trips' => $trips])
</x-filament-panels::page>
