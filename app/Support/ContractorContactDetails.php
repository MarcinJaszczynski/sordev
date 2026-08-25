<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\ContractorLocation;

class ContractorContactDetails
{
    public static function formatAddress(?Contractor $contractor): ?string
    {
        if (! $contractor) {
            return null;
        }

        return self::formatAddressParts(
            $contractor->street,
            $contractor->house_number,
            $contractor->postal_code,
            $contractor->city,
            $contractor->region,
            $contractor->country,
        );
    }

    public static function formatLocationAddress(?ContractorLocation $location): ?string
    {
        if (! $location) {
            return null;
        }

        return self::formatAddressParts(
            $location->street,
            $location->house_number,
            $location->postal_code,
            $location->city,
            $location->region,
            $location->country,
        );
    }

    /**
     * @return array{phone: ?string, email: ?string}
     */
    public static function contactMeta(?Contact $contact): array
    {
        return [
            'phone' => filled($contact?->phone) ? trim((string) $contact->phone) : null,
            'email' => filled($contact?->email) ? trim((string) $contact->email) : null,
        ];
    }

    /**
     * @return array{address: ?string, phone: ?string, email: ?string, bank_account: ?string}
     */
    public static function contractorMeta(?Contractor $contractor, ?Contact $contact = null): array
    {
        $contactMeta = self::contactMeta($contact);

        return [
            'address' => self::formatAddress($contractor),
            'phone' => $contactMeta['phone'] ?? (filled($contractor?->phone) ? trim((string) $contractor->phone) : null),
            'email' => $contactMeta['email'] ?? (filled($contractor?->email) ? trim((string) $contractor->email) : null),
            'bank_account' => filled($contractor?->bank_account) ? trim((string) $contractor->bank_account) : null,
        ];
    }

    /**
     * @return array{
     *     address: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     bank_account: ?string,
     *     branch_name: ?string,
     *     contact_name: ?string,
     *     company_name: ?string
     * }
     */
    public static function operationalMeta(
        ?Contractor $contractor,
        ?ContractorLocation $location = null,
        ?Contact $contact = null,
    ): array {
        $bankAccount = filled($contractor?->bank_account) ? trim((string) $contractor->bank_account) : null;

        if ($location) {
            return [
                'address' => self::formatLocationAddress($location) ?? self::formatAddress($contractor),
                'phone' => filled($location->phone)
                    ? trim((string) $location->phone)
                    : (filled($contractor?->phone) ? trim((string) $contractor->phone) : null),
                'email' => filled($location->email)
                    ? trim((string) $location->email)
                    : (filled($contractor?->email) ? trim((string) $contractor->email) : null),
                'bank_account' => $bankAccount,
                'branch_name' => filled($location->name) ? trim((string) $location->name) : null,
                'contact_name' => $location->contactDisplayName(),
                'company_name' => $contractor?->displayLabel(),
            ];
        }

        $meta = self::contractorMeta($contractor, $contact);

        return [
            'address' => $meta['address'],
            'phone' => $meta['phone'],
            'email' => $meta['email'],
            'bank_account' => $meta['bank_account'],
            'branch_name' => null,
            'contact_name' => $contact?->displayName(),
            'company_name' => $contractor?->displayLabel(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function displayLines(?Contractor $contractor, ?Contact $contact = null): array
    {
        return self::operationalDisplayLines($contractor, null, $contact);
    }

    /**
     * @return array<int, string>
     */
    public static function operationalDisplayLines(
        ?Contractor $contractor,
        ?ContractorLocation $location = null,
        ?Contact $contact = null,
    ): array {
        $lines = [];
        $meta = self::operationalMeta($contractor, $location, $contact);

        if ($meta['company_name'] && $location) {
            $lines[] = $meta['company_name'];
        }

        if ($meta['branch_name']) {
            $lines[] = $meta['branch_name'];
        }

        if (! $location && $contact) {
            $lines[] = $contact->displayName();
        } elseif ($meta['contact_name']) {
            $lines[] = $meta['contact_name'];
        }

        if ($meta['address']) {
            $lines[] = $meta['address'];
        }

        if ($meta['phone']) {
            $lines[] = 'tel. '.$meta['phone'];
        }

        if ($meta['email']) {
            $lines[] = $meta['email'];
        }

        if (filled($meta['bank_account'] ?? null)) {
            $lines[] = 'konto '.$meta['bank_account'];
        }

        return $lines;
    }

    public static function inlineSummary(?Contractor $contractor, ?Contact $contact = null): string
    {
        return collect(self::displayLines($contractor, $contact))->implode(' · ');
    }

    public static function operationalInlineSummary(
        ?Contractor $contractor,
        ?ContractorLocation $location = null,
        ?Contact $contact = null,
    ): string {
        return collect(self::operationalDisplayLines($contractor, $location, $contact))->implode(' · ');
    }

    private static function formatAddressParts(
        mixed $street,
        mixed $houseNumber,
        mixed $postalCode,
        mixed $city,
        mixed $region,
        mixed $country,
    ): ?string {
        $streetLine = trim(implode(' ', array_filter([
            filled($street) ? trim((string) $street) : null,
            filled($houseNumber) ? trim((string) $houseNumber) : null,
        ])));

        $cityLine = trim(implode(' ', array_filter([
            filled($postalCode) ? trim((string) $postalCode) : null,
            filled($city) ? trim((string) $city) : null,
        ])));

        $address = collect([$streetLine, $cityLine])
            ->when(filled($region), fn ($parts) => $parts->push(trim((string) $region)))
            ->when(filled($country), fn ($parts) => $parts->push(trim((string) $country)))
            ->filter()
            ->implode(', ');

        return $address !== '' ? $address : null;
    }
}
