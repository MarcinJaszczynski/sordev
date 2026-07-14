@props([
    'contractor' => null,
    'location' => null,
    'contact' => null,
    'showContactName' => true,
    'addressLabel' => null,
    'class' => '',
])

@php
    use App\Support\ContractorContactDetails;

    $meta = $location
        ? ContractorContactDetails::operationalMeta($contractor, $location, $contact)
        : ContractorContactDetails::contractorMeta($contractor, $contact);
    $lines = $location
        ? ContractorContactDetails::operationalDisplayLines($contractor, $location, $contact)
        : ContractorContactDetails::displayLines($contractor, $contact);
@endphp

@if ($lines !== [])
    <div {{ $attributes->merge(['class' => 'contractor-contact-details '.$class]) }}>
        @if ($location && filled($meta['branch_name'] ?? null))
            <div class="font-medium text-inherit">{{ $meta['branch_name'] }}</div>
        @elseif ($contact && $showContactName)
            <div class="font-medium text-inherit">{{ $contact->displayName() }}</div>
        @elseif (filled($meta['contact_name'] ?? null))
            <div class="font-medium text-inherit">{{ $meta['contact_name'] }}</div>
        @endif

        @if ($meta['address'])
            <div class="contractor-contact-details__line">
                @if ($addressLabel)
                    <span class="text-gray-500">{{ $addressLabel }}:</span>
                @endif
                {{ $meta['address'] }}
            </div>
        @endif

        @if ($meta['phone'] || $meta['email'])
            <div class="contractor-contact-details__line flex flex-wrap gap-x-2 gap-y-0.5">
                @if ($meta['phone'])
                    <a href="tel:{{ preg_replace('/\s+/', '', $meta['phone']) }}" class="hover:underline">{{ $meta['phone'] }}</a>
                @endif
                @if ($meta['phone'] && $meta['email'])
                    <span aria-hidden="true">·</span>
                @endif
                @if ($meta['email'])
                    <a href="mailto:{{ $meta['email'] }}" class="hover:underline">{{ $meta['email'] }}</a>
                @endif
            </div>
        @endif
    </div>
@endif
