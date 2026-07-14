<?php

namespace App\Services\Tfg;

use App\Models\Contract;
use App\Services\Tfg\Exceptions\TfgValidationException;
use Illuminate\Support\Collection;

class TfgContractValidator
{
    public const MAX_VARIANTS = 50;

    public const MAX_LOCATIONS = 5;

    public const MAX_TRANSPORTS = 3;

    public const MAX_ICAO = 3;

    public const MAX_PAYMENTS = 286;

    public const MAX_REFUNDS = 286;

    public function validate(Contract $contract): void
    {
        $errors = $this->collectErrors($contract);

        if ($errors !== []) {
            throw new TfgValidationException('Contract failed UFG validation.', $errors);
        }
    }

    public function collectErrors(Contract $contract): array
    {
        $contract->loadMissing(['variants.locations', 'variants.transports', 'payments', 'refunds']);

        $errors = [];

        if (blank($contract->contract_number)) {
            $errors[] = ['field' => 'contract_number', 'message' => 'Numer umowy jest wymagany.'];
        }

        if (blank($contract->subject_code)) {
            $errors[] = ['field' => 'subject_code', 'message' => 'Przedmiot umowy jest wymagany.'];
        }

        $variants = $contract->variants;
        if ($variants->count() === 0) {
            $errors[] = ['field' => 'variants', 'message' => 'Wymagany co najmniej jeden wariant.'];
        }

        if ($variants->count() > self::MAX_VARIANTS) {
            $errors[] = ['field' => 'variants', 'message' => 'Maksymalnie '.self::MAX_VARIANTS.' wariantów.'];
        }

        foreach ($variants as $variantIndex => $variant) {
            if ($variant->locations->count() > self::MAX_LOCATIONS) {
                $errors[] = ['field' => "variants.{$variantIndex}.locations", 'message' => 'Maksymalnie '.self::MAX_LOCATIONS.' lokalizacji.'];
            }

            if ($variant->transports->count() > self::MAX_TRANSPORTS) {
                $errors[] = ['field' => "variants.{$variantIndex}.transports", 'message' => 'Maksymalnie '.self::MAX_TRANSPORTS.' transportów.'];
            }

            foreach ($variant->transports as $transportIndex => $transport) {
                if (\App\Models\TfgDictionaryItem::requiresIcao($transport->transport_code)) {
                    $icao = (array) ($transport->icao_codes ?? []);
                    if ($icao === []) {
                        $errors[] = ['field' => "variants.{$variantIndex}.transports.{$transportIndex}.icao_codes", 'message' => 'Kody ICAO są wymagane dla lotu.'];
                    }
                    if (count($icao) > self::MAX_ICAO) {
                        $errors[] = ['field' => "variants.{$variantIndex}.transports.{$transportIndex}.icao_codes", 'message' => 'Maksymalnie '.self::MAX_ICAO.' kodów ICAO.'];
                    }
                    foreach ($icao as $code) {
                        if (! preg_match('/^[A-Z]{4}$/', (string) $code)) {
                            $errors[] = ['field' => "variants.{$variantIndex}.transports.{$transportIndex}.icao_codes", 'message' => "Nieprawidłowy kod ICAO: {$code}"];
                        }
                    }
                }
            }
        }

        if ($contract->payments->count() > self::MAX_PAYMENTS) {
            $errors[] = ['field' => 'payments', 'message' => 'Maksymalnie '.self::MAX_PAYMENTS.' wpłat.'];
        }

        if ($contract->refunds->count() > self::MAX_REFUNDS) {
            $errors[] = ['field' => 'refunds', 'message' => 'Maksymalnie '.self::MAX_REFUNDS.' zwrotów.'];
        }

        return $errors;
    }

    public function validateCollection(Collection $contracts): array
    {
        $allErrors = [];

        foreach ($contracts as $contract) {
            $errors = $this->collectErrors($contract);
            if ($errors !== []) {
                $allErrors[$contract->id] = $errors;
            }
        }

        return $allErrors;
    }
}
