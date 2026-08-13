@php
    /** @var \App\Models\Contractor|null $contractor */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Contact> $contacts */
    $contacts = $contacts ?? collect();
@endphp

<div class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
    @if ($contractor)
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Karta kontrahenta</p>
            <p class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $contractor->displayLabel() }}</p>
            <x-contractor-contact-details
                :contractor="$contractor"
                :show-contact-name="false"
                class="mt-1 text-gray-600 dark:text-gray-300"
            />
            @if (blank($contractor->phone) && blank($contractor->email))
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Brak telefonu/e-mail na karcie — uzupełnij lub dodaj kontakt poniżej.</p>
            @endif
        </div>

        <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kontakty powiązane</p>

            @if ($contacts->isEmpty())
                <p class="mt-1 text-gray-600 dark:text-gray-300">Brak powiązanych kontaktów. Dodaj osobę kontaktową bezpośrednio tutaj.</p>
            @else
                <ul class="mt-2 space-y-2">
                    @foreach ($contacts as $contact)
                        <li class="rounded-md border border-gray-200 bg-white px-2.5 py-2 dark:border-gray-700 dark:bg-gray-950/40">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $contact->displayName() }}</p>
                            <div class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-gray-600 dark:text-gray-300">
                                @if (filled($contact->phone))
                                    <a href="tel:{{ preg_replace('/\s+/', '', $contact->phone) }}" class="hover:underline">{{ $contact->phone }}</a>
                                @endif
                                @if (filled($contact->phone) && filled($contact->email))
                                    <span aria-hidden="true">·</span>
                                @endif
                                @if (filled($contact->email))
                                    <a href="mailto:{{ $contact->email }}" class="hover:underline">{{ $contact->email }}</a>
                                @endif
                                @if (blank($contact->phone) && blank($contact->email))
                                    <span class="text-amber-700 dark:text-amber-300">Brak telefonu/e-mail</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <p class="text-gray-600 dark:text-gray-300">Wybierz kontrahenta, aby zobaczyć dane kontaktowe.</p>
    @endif
</div>
