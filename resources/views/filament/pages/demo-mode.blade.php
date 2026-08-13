<x-filament-panels::page>
    @php
        /** @var array{enabled: bool, email: string, source: string} $status */
        $status = $status ?? ['enabled' => false, 'email' => '', 'source' => 'off'];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <x-slot name="description">
                Ustawienia zapisują się w bazie i działają od kolejnego requestu (po zapisaniu odśwież stronę / wyślij testowy mail).
            </x-slot>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Stan</dt>
                    <dd class="font-medium">
                        @if($status['enabled'])
                            <span class="text-warning-600 dark:text-warning-400">Włączony</span>
                        @else
                            <span class="text-success-600 dark:text-success-400">Wyłączony</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Źródło</dt>
                    <dd class="font-medium">
                        @switch($status['source'])
                            @case('panel')
                                Panel (ta strona)
                                @break
                            @case('env')
                                MAIL_DEMO_TO w .env (fallback)
                                @break
                            @default
                                Brak
                        @endswitch
                    </dd>
                </div>
                @if($status['enabled'])
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Aktualny odbiorca demo</dt>
                        <dd class="font-medium">{{ $status['email'] }}</dd>
                    </div>
                @endif
                @if(filled($envFallback) && $status['source'] !== 'env')
                    <div class="sm:col-span-2 text-gray-500 dark:text-gray-400">
                        W .env jest też <code>MAIL_DEMO_TO={{ $envFallback }}</code>
                        — używany tylko gdy w panelu nie zapisano jeszcze ustawienia.
                    </div>
                @endif
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Ustawienia</x-slot>
            <form wire:submit="save" class="space-y-4">
                {{ $this->form }}

                <div class="flex justify-start">
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Zapisz
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Uwagi</x-slot>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Na produkcji tryb demo powinien być <strong>wyłączony</strong>.</li>
                <li>Odbiorca biura dla wniosków / zapytań to osobno <code>MAIL_INQUIRIES_TO</code> (np. rafa@bprafa.pl) — w trybie demo i tak wszystko pójdzie na adres demo.</li>
                <li>Po zapisaniu wyślij np. wniosek o fakturę albo formularz kontaktowy, żeby sprawdzić skrzynkę.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
