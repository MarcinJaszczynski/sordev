@php
    /** @var \App\Models\Contact|null $contact */
    /** @var \App\Models\Contractor|null $contractor */
@endphp

<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900/40">
    @if ($contractor)
        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $contractor->displayLabel() }}</p>
    @endif

    <x-contractor-contact-details
        :contractor="$contractor"
        :contact="$contact"
        :show-contact-name="false"
        class="mt-1 text-gray-600 dark:text-gray-300"
    />
</div>
