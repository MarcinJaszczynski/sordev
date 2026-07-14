<?php

namespace App\Filament\Forms;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Services\ContractGroupPricingService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ContractGroupPricingFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(?\Closure $resolveEvent = null): array
    {
        return [
            Forms\Components\TextInput::make('unit_price')
                ->label('Cena jednostkowa (za osobę)')
                ->numeric()
                ->minValue(0)
                ->suffix('PLN')
                ->visible(fn (Get $get): bool => static::isGroupType($get('agreement_type')))
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get) => static::syncGroupTotal($set, $get, $resolveEvent))
                ->helperText('Dla umowy grupowej: kwota łączna = cena za osobę × liczba uczestników.'),

            Forms\Components\Select::make('payment_scheme')
                ->label('Schemat płatności')
                ->options(fn (Get $get): array => static::paymentSchemeOptions($get('agreement_type')))
                ->default(Contract::PAYMENT_SCHEME_LUMP_SUM)
                ->live()
                ->visible(fn (Get $get): bool => static::supportsPaymentScheme($get('agreement_type')))
                ->required(fn (Get $get): bool => static::supportsPaymentScheme($get('agreement_type'))),

            ...static::installmentRepeater($resolveEvent),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function installmentRepeater(?\Closure $resolveEvent): array
    {
        return [
            Forms\Components\Repeater::make('payment_schedules')
                ->label('Harmonogram transz')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Opis transzy')
                        ->maxLength(255)
                        ->placeholder('np. Zaliczka'),

                    Forms\Components\TextInput::make('amount')
                        ->label('Kwota')
                        ->numeric()
                        ->minValue(0.01)
                        ->required()
                        ->suffix('PLN'),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Termin płatności')
                        ->native(false),

                    Forms\Components\Textarea::make('notes')
                        ->label('Uwagi')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(2)
                ->addActionLabel('Dodaj transzę')
                ->reorderable()
                ->collapsible()
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => static::supportsInstallments($get('agreement_type'), $get('payment_scheme')))
                ->helperText(fn (Get $get): ?string => static::installmentsHelperText($get('payment_schedules'), $get('amount_due'))),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentSchemeOptions(mixed $agreementType): array
    {
        if (static::isIndividualType($agreementType)) {
            return collect(Contract::$paymentSchemes)
                ->except(Contract::PAYMENT_SCHEME_INDIVIDUAL)
                ->all();
        }

        return Contract::$paymentSchemes;
    }

    public static function supportsPaymentScheme(mixed $agreementType): bool
    {
        return static::isGroupType($agreementType) || static::isIndividualType($agreementType);
    }

    public static function supportsInstallments(mixed $agreementType, mixed $paymentScheme): bool
    {
        return static::supportsPaymentScheme($agreementType)
            && ($paymentScheme ?? Contract::PAYMENT_SCHEME_LUMP_SUM) === Contract::PAYMENT_SCHEME_INSTALLMENTS;
    }

    public static function isGroupType(mixed $agreementType): bool
    {
        return in_array($agreementType, [Contract::TYPE_GROUP, EventAgreement::TYPE_GROUP], true);
    }

    public static function isIndividualType(mixed $agreementType): bool
    {
        return in_array($agreementType, [Contract::TYPE_INDIVIDUAL, EventAgreement::TYPE_INDIVIDUAL], true);
    }

    public static function syncGroupTotal(Set $set, Get $get, ?\Closure $resolveEvent = null): void
    {
        if (! static::isGroupType($get('agreement_type'))) {
            return;
        }

        $event = $resolveEvent ? $resolveEvent() : null;
        $participantCount = max(1, (int) ($get('participant_count') ?? 1));
        $unitPrice = (float) ($get('unit_price') ?? 0);

        if ($unitPrice <= 0 && $event instanceof Event) {
            $unitPrice = (float) $event->resolvedPricePerPerson($participantCount);
            $set('unit_price', $unitPrice);
        }

        if ($unitPrice > 0) {
            $set('amount_due', round($unitPrice * $participantCount, 2));
        }
    }

    public static function installmentsHelperText(mixed $schedules, mixed $amountDue): ?string
    {
        $normalized = app(ContractGroupPricingService::class)->normalizedSchedules(is_array($schedules) ? $schedules : []);
        $sum = app(ContractGroupPricingService::class)->schedulesSum($normalized);
        $target = round((float) $amountDue, 2);

        if ($target <= 0 || $sum <= 0) {
            return 'Podziel kwotę umowy na transze z terminami płatności.';
        }

        if (abs($sum - $target) > 0.01) {
            return sprintf(
                'Suma transz: %s PLN (umowa: %s PLN)',
                number_format($sum, 2, ',', ' '),
                number_format($target, 2, ',', ' '),
            );
        }

        return sprintf('Suma transz zgadza się z kwotą umowy (%s PLN).', number_format($target, 2, ',', ' '));
    }
}
