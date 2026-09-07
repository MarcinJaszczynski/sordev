<?php

namespace App\Enums;

enum ContractorSettlementForm: string
{
    case ContractOfWork = 'contract_of_work';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::ContractOfWork => 'Umowa o dzieło',
            self::Invoice => 'Faktura',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
