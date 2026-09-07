<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Services\ProgramPointPricingCalculator;
use App\Support\ProgramPointCostPricing;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Schema;

class EventProgramPointPricingFields
{
    /**
     * @param  array{
     *     default_participant_count?: int,
     *     gratis_count?: int,
     *     pilot_count?: int,
     *     driver_count?: int,
     *     pricing_basis_selector?: bool,
     *     for_template?: bool,
     * }  $options
     */
    public static function section(array $options = []): Forms\Components\Section
    {
        $forTemplate = (bool) ($options['for_template'] ?? false);
        $defaultParticipants = max(1, (int) ($options['default_participant_count'] ?? 1));
        $gratisCount = max(0, (int) ($options['gratis_count'] ?? 0));
        $pilotCount = max(0, (int) ($options['pilot_count'] ?? ($forTemplate ? 1 : 0)));
        $driverCount = max(0, (int) ($options['driver_count'] ?? ($forTemplate ? 1 : 0)));
        $showBasisSelector = (bool) ($options['pricing_basis_selector'] ?? false);

        $schema = [];

        if ($showBasisSelector) {
            $schema[] = Forms\Components\ToggleButtons::make('pricing_basis')
                ->label('Rodzaj ceny')
                ->options([
                    ProgramPointPricingCalculator::BASIS_PER_PERSON => 'Za osobę',
                    ProgramPointPricingCalculator::BASIS_PER_GROUP => 'Za grupę',
                    ProgramPointPricingCalculator::BASIS_PER_PIECE => 'Za sztukę',
                ])
                ->default(ProgramPointPricingCalculator::BASIS_PER_PERSON)
                ->inline()
                ->live()
                ->dehydrated(false)
                ->columnSpanFull()
                ->afterStateHydrated(function (Forms\Components\ToggleButtons $component, $state, $record) use ($forTemplate): void {
                    if ($record instanceof EventProgramPoint || ($forTemplate && $record instanceof EventTemplateProgramPoint)) {
                        $component->state(ProgramPointPricingCalculator::pricingBasisFromGroupSize($record->group_size));
                    }
                })
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($defaultParticipants, $gratisCount, $pilotCount, $driverCount): void {
                    self::applyPricingBasis((string) $state, $set, $get, $defaultParticipants, true, $gratisCount, $pilotCount, $driverCount);
                });

            $schema[] = Forms\Components\Hidden::make('group_size')
                ->dehydrated()
                ->default(1)
                ->afterStateHydrated(function (Forms\Components\Hidden $component, $state, $record): void {
                    if ($record) {
                        $component->state($record->group_size ?? 1);
                    }
                });

            $schema[] = Forms\Components\TextInput::make('group_size_edit')
                ->label('Wielkość grupy')
                ->numeric()
                ->minValue(2)
                ->default(20)
                ->helperText('Np. 20 — cena dotyczy jednej grupy 20 osób.')
                ->visible(fn (Get $get): bool => ($get('pricing_basis') ?? ProgramPointPricingCalculator::BASIS_PER_PERSON)
                    === ProgramPointPricingCalculator::BASIS_PER_GROUP)
                ->dehydrated(false)
                ->live(onBlur: true)
                ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record, Get $get): void {
                    $component->state(max(2, (int) ($get('group_size') ?: ($record?->group_size ?? 20))));
                })
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($defaultParticipants, $gratisCount, $pilotCount, $driverCount): void {
                    $set('group_size', max(2, (int) ($state ?: 20)));
                    self::syncTotals($set, $get, $defaultParticipants, false, $gratisCount, $pilotCount, $driverCount);
                });
        }

        $groupSizeField = $showBasisSelector
            ? null
            : Forms\Components\TextInput::make('group_size')
                ->label('Wielkość grupy')
                ->numeric()
                ->minValue(fn (Get $get): int => 0)
                ->default(1)
                ->helperText('1 = cena za osobę. >1 = cena za grupę (np. 20). 0 = liczba sztuk poniżej.')
                ->visible(true)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotals($set, $get, $defaultParticipants, false, $gratisCount, $pilotCount, $driverCount));

        $pricingFields = [
            Forms\Components\TextInput::make('unit_price')
                ->label(function (Get $get) use ($showBasisSelector): string {
                    if ($showBasisSelector) {
                        $basis = (string) ($get('pricing_basis') ?? ProgramPointPricingCalculator::BASIS_PER_PERSON);

                        return ProgramPointPricingCalculator::unitPriceLabelForBasis($basis);
                    }

                    return ProgramPointPricingCalculator::unitPriceLabel(
                        filled($get('group_size')) ? (int) $get('group_size') : 1
                    );
                })
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0)
                ->required($forTemplate)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotals($set, $get, $defaultParticipants, false, $gratisCount, $pilotCount, $driverCount)),

            ...($groupSizeField ? [$groupSizeField] : []),
        ];

        if (! $forTemplate) {
            $pricingFields[] = Forms\Components\TextInput::make('quantity')
                ->label($showBasisSelector ? 'Liczba sztuk' : 'Ilość (stała)')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->dehydrated()
                ->helperText($showBasisSelector
                    ? 'Np. liczba butelek alkoholu — niezależna od liczby uczestników.'
                    : 'Tylko gdy wielkość grupy = 0. Inaczej liczy się automatycznie.')
                ->visible(function (Get $get) use ($showBasisSelector): bool {
                    if ($showBasisSelector) {
                        return ($get('pricing_basis') ?? ProgramPointPricingCalculator::BASIS_PER_PERSON)
                            === ProgramPointPricingCalculator::BASIS_PER_PIECE;
                    }

                    return (int) ($get('group_size') ?? 1) <= 0;
                })
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncTotals($set, $get, $defaultParticipants, false, $gratisCount, $pilotCount, $driverCount));

            $pricingFields[] = Forms\Components\Select::make('unit')
                ->label('Jednostka')
                ->options([
                    'szt.' => 'szt.',
                    'os.' => 'os.',
                    'gr.' => 'gr.',
                    'noc' => 'noc',
                    'godz.' => 'godz.',
                    'km' => 'km',
                ])
                ->default('szt.')
                ->visible(fn (): bool => Schema::hasColumn((new EventProgramPoint)->getTable(), 'unit'))
                ->searchable();
        }

        $pricingFields = array_merge($pricingFields, [
            CurrencyConversionFields::currencySelect(),
            CurrencyConversionFields::convertToggle(),
            CurrencyConversionFields::plnPreview('unit_price'),

            ...self::costHeadcountToggles($defaultParticipants, $gratisCount, $pilotCount, $driverCount, $forTemplate),

            Forms\Components\Placeholder::make('pricing_breakdown_preview')
                ->label('Podgląd wyliczenia')
                ->content(fn (Get $get): string => self::previewText($get, $defaultParticipants, $gratisCount, $pilotCount, $driverCount))
                ->extraAttributes(['class' => 'epp-pricing-preview whitespace-pre-line'])
                ->columnSpanFull(),
        ]);

        if (! $forTemplate) {
            $pricingFields = array_merge($pricingFields, [
                Forms\Components\TextInput::make('calculated_price')
                    ->label('Szablon')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Zapis przy zapisie punktu.'),

                Forms\Components\TextInput::make('planned_price')
                    ->label('Planowane')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->live(onBlur: true),

                Forms\Components\TextInput::make('paid_price')
                    ->label('Zapłacono')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0),
            ]);
        }

        $schema = array_merge($schema, $pricingFields);

        return Forms\Components\Section::make($showBasisSelector ? 'Wycena i rozliczenie' : 'Wycena')
            ->description($showBasisSelector
                ? 'Wybierz rodzaj ceny — kwota trafi do szablonu imprezy i kosztów rozliczenia.'
                : ($forTemplate
                    ? 'Ustawienia finansowe punktu programu w bibliotece szablonu.'
                    : 'Ten sam algorytm co w bibliotece punktów szablonu.'))
            ->icon('heroicon-o-currency-dollar')
            ->columns($forTemplate ? 2 : 3)
            ->schema($schema);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function costHeadcountToggles(
        int $participantCount = 1,
        int $gratisCount = 0,
        int $pilotCount = 0,
        int $driverCount = 0,
        bool $forTemplate = false,
    ): array {
        $sync = fn (Set $set, Get $get) => self::syncTotals(
            $set,
            $get,
            $participantCount,
            false,
            $gratisCount,
            $pilotCount,
            $driverCount,
        );

        $pilotHelper = $forTemplate
            ? 'Domyślnie odznaczone. Zaznacz, aby na imprezie doliczyć pilota (gdy będzie przypisany).'
            : ($pilotCount > 0
                ? 'Domyślnie odznaczone. Zaznacz, aby doliczyć pilota (1 os.).'
                : 'Domyślnie odznaczone. Zaznaczysz, gdy impreza będzie miała pilota.');

        $driverHelper = $forTemplate
            ? 'Domyślnie odznaczone. Zaznacz, aby na imprezie doliczyć kierowców z wariantu ilości.'
            : ($driverCount > 0
                ? 'Domyślnie odznaczone. Zaznacz, aby doliczyć kierowcę ('.$driverCount.' os.).'
                : 'Domyślnie odznaczone. W wariancie ilości nie ma kierowcy.');

        return [
            Forms\Components\Fieldset::make('Kogo doliczyć do kosztu')
                ->schema([
                    Forms\Components\Toggle::make('include_gratis_in_cost')
                        ->label('Liczyć z opiekunami / gratisami')
                        ->helperText($forTemplate
                            ? 'Domyślnie odznaczone. Zaznacz, gdy koszt dotyczy też opiekunów.'
                            : 'Domyślnie odznaczone. Zaznacz, aby doliczyć opiekunów ('.$gratisCount.' os.).')
                        ->default(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, $state): void {
                            $component->state((bool) $state);
                        })
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $sync($set, $get))
                        ->inline(false),

                    Forms\Components\Toggle::make('include_pilot_in_cost')
                        ->label('Liczyć z pilotem')
                        ->helperText($pilotHelper)
                        ->default(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, $state): void {
                            $component->state((bool) $state);
                        })
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $sync($set, $get))
                        ->inline(false),

                    Forms\Components\Toggle::make('include_driver_in_cost')
                        ->label('Liczyć z kierowcą')
                        ->helperText($driverHelper)
                        ->default(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, $state): void {
                            $component->state((bool) $state);
                        })
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $sync($set, $get))
                        ->inline(false),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ];
    }

    public static function applyPricingBasis(
        string $basis,
        Set $set,
        Get $get,
        int $participantCount = 1,
        bool $syncTotals = true,
        int $gratisCount = 0,
        int $pilotCount = 0,
        int $driverCount = 0,
    ): void {
        match ($basis) {
            ProgramPointPricingCalculator::BASIS_PER_PIECE => $set('group_size', 0),
            ProgramPointPricingCalculator::BASIS_PER_GROUP => $set(
                'group_size',
                max(2, (int) ($get('group_size') ?: 20) <= 1 ? 20 : (int) $get('group_size')),
            ),
            default => $set('group_size', 1),
        };

        if ($basis === ProgramPointPricingCalculator::BASIS_PER_PIECE && blank($get('quantity'))) {
            $set('quantity', 1);
        }

        if ($syncTotals) {
            self::syncTotals($set, $get, $participantCount, false, $gratisCount, $pilotCount, $driverCount);
        }
    }

    public static function applyTemplateDefaults(Set $set, EventTemplateProgramPoint $template): void
    {
        $groupSize = (int) ($template->group_size ?? 1);

        $set('unit_price', $template->unit_price);
        $set('group_size', $groupSize > 0 ? $groupSize : 1);
        $set('quantity', 1);
        $set('currency_id', $template->currency_id);
        $set('convert_to_pln', (bool) ($template->convert_to_pln ?? false));

        $total = ProgramPointPricingCalculator::totalPrice(
            (float) ($template->unit_price ?? 0),
            1,
            $groupSize > 0 ? $groupSize : 1,
        );

        $set('planned_price', $total);
        $set('calculated_price', $total);
        $set('include_gratis_in_cost', (bool) ($template->include_gratis_in_cost ?? false));
        $set('include_pilot_in_cost', (bool) ($template->include_pilot_in_cost ?? false));
        $set('include_driver_in_cost', (bool) ($template->include_driver_in_cost ?? false));
    }

    public static function applyEventPointDefaults(Set $set, EventProgramPoint $point): void
    {
        $set('unit_price', $point->unit_price);
        $set('group_size', $point->group_size ?? 1);
        $set('quantity', $point->quantity ?? 1);
        $set('currency_id', $point->currency_id);
        $set('convert_to_pln', (bool) ($point->convert_to_pln ?? false));
        $set('planned_price', $point->planned_price ?? $point->total_price);
        $set('paid_price', $point->paid_price ?? 0);
        $set('calculated_price', $point->calculated_price ?? $point->total_price);
        $set('include_gratis_in_cost', (bool) ($point->include_gratis_in_cost ?? false));
        $set('include_pilot_in_cost', (bool) ($point->include_pilot_in_cost ?? false));
        $set('include_driver_in_cost', (bool) ($point->include_driver_in_cost ?? false));
    }

    public static function syncTotals(
        Set $set,
        Get $get,
        int $participantCount = 1,
        bool $forcePlanned = false,
        int $gratisCount = 0,
        int $pilotCount = 0,
        int $driverCount = 0,
    ): void {
        $unit = (float) ($get('unit_price') ?: 0);
        $groupSize = $get('group_size');
        $groupSizeInt = $groupSize === null || $groupSize === '' ? 1 : (int) $groupSize;
        $fixedQty = max(1, (int) ($get('quantity') ?: 1));
        $headcount = ProgramPointCostPricing::applyIncludedExtras(
            max(1, $participantCount),
            $gratisCount,
            $pilotCount,
            $driverCount,
            (bool) ($get('include_gratis_in_cost') ?? false),
            (bool) ($get('include_pilot_in_cost') ?? false),
            (bool) ($get('include_driver_in_cost') ?? false),
        );

        $total = ProgramPointPricingCalculator::totalPrice(
            $unit,
            $headcount,
            $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            $fixedQty,
        );

        $set('calculated_price', $total);

        $planned = $get('planned_price');
        if ($forcePlanned || blank($planned) || (float) $planned == 0.0) {
            $set('planned_price', $total);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergePricingIntoPayload(
        array $data,
        float $unitPrice,
        int $participantCount,
        int $gratisCount = 0,
        int $pilotCount = 0,
        int $driverCount = 0,
    ): array {
        $groupSizeRaw = $data['group_size'] ?? 1;
        $groupSizeInt = $groupSizeRaw === null || $groupSizeRaw === '' ? 1 : (int) $groupSizeRaw;
        $fixedQty = max(1, (int) ($data['quantity'] ?? 1));
        $headcount = ProgramPointCostPricing::applyIncludedExtras(
            max(1, $participantCount),
            $gratisCount,
            $pilotCount,
            $driverCount,
            (bool) ($data['include_gratis_in_cost'] ?? false),
            (bool) ($data['include_pilot_in_cost'] ?? false),
            (bool) ($data['include_driver_in_cost'] ?? false),
        );

        $quantity = ProgramPointPricingCalculator::billableUnits(
            $headcount,
            $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            $fixedQty,
        );

        $total = ProgramPointPricingCalculator::totalPrice(
            $unitPrice,
            $headcount,
            $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            $fixedQty,
        );

        $planned = isset($data['planned_price']) && $data['planned_price'] !== '' && $data['planned_price'] !== null
            ? (float) $data['planned_price']
            : $total;

        return array_merge($data, [
            'unit_price' => $unitPrice,
            'group_size' => $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            'quantity' => $quantity,
            'total_price' => $total,
            'currency_id' => $data['currency_id'] ?? null,
            'convert_to_pln' => (bool) ($data['convert_to_pln'] ?? false),
            'planned_price' => $planned,
            'paid_price' => (float) ($data['paid_price'] ?? 0),
            'calculated_price' => $total,
        ]);
    }

    protected static function previewText(
        Get $get,
        int $participantCount,
        int $gratisCount = 0,
        int $pilotCount = 0,
        int $driverCount = 0,
    ): string {
        $unit = (float) ($get('unit_price') ?: 0);
        if ($unit <= 0) {
            return 'Podaj cenę jednostkową.';
        }

        $groupSizeRaw = $get('group_size');
        $groupSizeInt = $groupSizeRaw === null || $groupSizeRaw === '' ? 1 : (int) $groupSizeRaw;
        $currency = $get('currency_id') ? Currency::find($get('currency_id')) : null;
        $headcount = ProgramPointCostPricing::applyIncludedExtras(
            max(1, $participantCount),
            $gratisCount,
            $pilotCount,
            $driverCount,
            (bool) ($get('include_gratis_in_cost') ?? false),
            (bool) ($get('include_pilot_in_cost') ?? false),
            (bool) ($get('include_driver_in_cost') ?? false),
        );

        $breakdown = ProgramPointPricingCalculator::breakdown(
            $unit,
            $headcount,
            $groupSizeInt <= 0 ? 0 : $groupSizeInt,
            max(1, (int) ($get('quantity') ?: 1)),
            $currency,
            (bool) ($get('convert_to_pln') ?? false),
        );

        return ProgramPointPricingCalculator::describeBreakdown($breakdown);
    }
}
