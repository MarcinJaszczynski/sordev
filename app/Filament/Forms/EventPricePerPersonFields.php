<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Services\EventManualPricePerPersonService;
use App\Services\EventPriceSummaryService;
use App\Support\EventParticipantGroupLabels;
use App\Support\MoneyFormatter;
use Filament\Forms;
use Filament\Forms\Get;

class EventPricePerPersonFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function manualPriceFields(): array
    {
        return [
            Forms\Components\Fieldset::make('Cena za osobę')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Placeholder::make('calculated_price_per_person_preview')
                        ->label('Cena ze szablonu (do zapłaty)')
                        ->content(function (?Event $record, Get $get): string {
                            $count = max(1, (int) ($get('participant_count') ?? $record?->participant_count ?? 1));
                            $gratis = self::resolveGratisFromForm(
                                fn (string $key) => $get($key),
                                $record,
                                $count,
                            );

                            // Create: podgląd z szablonu
                            if (! $record) {
                                $templateId = (int) ($get('event_template_id') ?? 0);
                                $startPlaceId = (int) ($get('start_place_id') ?? 0);
                                if ($templateId <= 0 || $startPlaceId <= 0) {
                                    return 'Wybierz szablon i miejsce wyjazdu';
                                }

                                $template = EventTemplate::query()->find($templateId);
                                if (! $template) {
                                    return 'Brak szablonu';
                                }

                                $summary = app(EventPriceSummaryService::class)->forTemplate(
                                    $template,
                                    $startPlaceId,
                                    $count,
                                    $gratis,
                                );

                                return self::formatSummaryContent($summary);
                            }

                            $original = [
                                'transfer_km' => $record->getAttributes()['transfer_km'] ?? $record->transfer_km,
                                'program_km' => $record->getAttributes()['program_km'] ?? $record->program_km,
                                'start_place_id' => $record->getAttributes()['start_place_id'] ?? $record->start_place_id,
                                'participant_count' => $record->getAttributes()['participant_count'] ?? $record->participant_count,
                                'bus_id' => $record->getAttributes()['bus_id'] ?? $record->bus_id,
                            ];

                            // Nadpisuj tylko gdy form ma sensowną wartość.
                            // default(0) na km NIE może wyzerować realnego transferu/programu z rekordu.
                            self::applyKmOverride($record, 'transfer_km', $get('transfer_km'), $original['transfer_km']);
                            self::applyKmOverride($record, 'program_km', $get('program_km'), $original['program_km']);

                            $formStartPlace = $get('start_place_id');
                            if ($formStartPlace !== null && $formStartPlace !== '') {
                                $record->start_place_id = $formStartPlace;
                            }
                            $record->participant_count = $count;

                            $formBusId = $get('bus_id');
                            // Puste Select (przed fill / nullable) NIE czyści bus_id z rekordu.
                            if ($formBusId !== null && $formBusId !== '' && (int) $formBusId > 0) {
                                if ((int) ($record->bus_id ?? 0) !== (int) $formBusId) {
                                    $record->bus_id = (int) $formBusId;
                                }
                            }
                            // Zawsze odśwież relację — uniknij stale null przy poprawnym bus_id.
                            $record->unsetRelation('bus');
                            \App\Services\EventCostCalculator::clearRequestCache();

                            try {
                                $summary = app(EventPriceSummaryService::class)->forEvent(
                                    $record,
                                    $count,
                                    $gratis,
                                    includeNearest: true,
                                );

                                return self::formatSummaryContent($summary);
                            } finally {
                                foreach ($original as $attribute => $value) {
                                    $record->setAttribute($attribute, $value);
                                }
                                $record->unsetRelation('bus');
                            }
                        })
                        ->helperText(fn (): string => self::canViewCostBreakdown()
                            ? 'Baza (program+hotel+transport+ubezpieczenie) + marża + podatki ÷ płacący. Waluty obce osobno. Aktualizuje się przy zmianie uczestników / opiekunów / miejsca / km.'
                            : 'Cena do zapłaty za płacącego (z walutami). Aktualizuje się przy zmianie uczestników / opiekunów / miejsca / km.')
                        ->extraAttributes(['class' => 'whitespace-pre-line'])
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('effective_price_per_person_preview')
                        ->label('Obowiązująca cena')
                        ->content(function (?Event $record, Get $get): string {
                            if (! $record || ! (bool) $get('use_manual_price_per_person')) {
                                return 'Z kalkulacji (patrz wyżej)';
                            }

                            $service = app(EventManualPricePerPersonService::class);
                            $formLines = $get('manual_price_per_person_lines') ?? [];

                            if (empty($formLines)) {
                                return 'Dodaj co najmniej jedną kwotę w walucie poniżej';
                            }

                            $currencies = Currency::query()
                                ->whereIn('id', array_column($formLines, 'currency_id'))
                                ->get()
                                ->keyBy('id');

                            $lines = collect($formLines)
                                ->map(fn (array $line): array => [
                                    'amount' => (float) ($line['amount'] ?? 0),
                                    'currency_code' => $currencies[(int) ($line['currency_id'] ?? 0)]?->code ?? 'PLN',
                                ])
                                ->filter(fn (array $line): bool => $line['amount'] > 0)
                                ->all();

                            $label = $service->formatLinesLabel($lines);

                            return $label
                                ? $label.' (ustawiona ręcznie)'
                                : 'Dodaj co najmniej jedną kwotę w walucie poniżej';
                        })
                        ->visible(fn (Get $get): bool => (bool) $get('use_manual_price_per_person'))
                        ->hiddenOn('create'),

                    Forms\Components\Toggle::make('use_manual_price_per_person')
                        ->label('Zablokuj auto-przeliczanie — ustal ręcznie cenę za płacącego')
                        ->default(false)
                        ->live()
                        ->hiddenOn('create')
                        ->afterStateUpdated(fn ($livewire) => method_exists($livewire, 'dispatch')
                            ? $livewire->dispatch('event-price-table-refresh')
                            : null)
                        ->helperText('Po włączeniu kalkulacja nie nadpisze ceny za osobę. Możesz podać kilka walut naraz, np. 3100 PLN + 210 EUR.')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('manual_price_per_person_lines')
                        ->label('Kwoty ręczne')
                        ->schema([
                            Forms\Components\TextInput::make('amount')
                                ->label('Kwota')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->live(onBlur: true),
                            Forms\Components\Select::make('currency_id')
                                ->label('Waluta')
                                ->options(fn (): array => Currency::query()
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Currency $currency): array => [
                                        $currency->id => $currency->displayLabel(),
                                    ])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->default(fn (): ?int => Currency::query()
                                    ->where('code', 'PLN')
                                    ->orWhere('symbol', 'PLN')
                                    ->value('id')),
                        ])
                        ->columns(['default' => 1, 'md' => 2])
                        ->defaultItems(1)
                        ->addActionLabel('Dodaj walutę')
                        ->visible(fn (Get $get): bool => (bool) $get('use_manual_price_per_person'))
                        ->required(fn (Get $get): bool => (bool) $get('use_manual_price_per_person'))
                        ->minItems(1)
                        ->columnSpanFull()
                        ->hiddenOn('create')
                        ->afterStateUpdated(fn ($livewire) => method_exists($livewire, 'dispatch')
                            ? $livewire->dispatch('event-price-table-refresh')
                            : null)
                        ->helperText('Każda waluta tylko raz. Dotyczy uczestników płacących (bez '.EventParticipantGroupLabels::GRATIS_GENITIVE.', pilota i obsługi).'),
                ]),
        ];
    }

    /**
     * Gratis z formularza — default(0) nie może nadpisać opiekunów z wariantu qty rekordu.
     *
     * @param  callable(string): mixed  $get
     */
    public static function resolveGratisFromForm(callable $get, ?Event $record, int $paying): int
    {
        $resolved = $record
            ? max(0, $record->resolveGratisCountForParticipantCount($paying))
            : 0;

        $formGratis = $get('gratis_count');

        if ($formGratis === null || $formGratis === '') {
            return $resolved;
        }

        $formValue = max(0, (int) $formGratis);

        // default(0) przed fill / przy pierwszym renderze: nie ufaj zeru, gdy wariant ma opiekunów
        // i liczba płacących nadal odpowiada zapisanej na imprezie.
        if (
            $record
            && $formValue === 0
            && $resolved > 0
            && $paying === max(1, (int) ($record->participant_count ?? 1))
        ) {
            return $resolved;
        }

        return $formValue;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function formatSummaryContent(array $summary): string
    {
        if (! ($summary['ready'] ?? false)) {
            return (string) ($summary['message'] ?? 'Brak danych kalkulacji');
        }

        $lines = [
            'Za osobę: '.$summary['price_per_person_label'],
            'Suma grupy: '.MoneyFormatter::format(
                (float) ($summary['payable_total_pln'] ?? (
                    ((float) ($summary['price_per_person_rounded'] ?? 0)) * max(1, (int) ($summary['paying'] ?? 1))
                )),
                'PLN'
            ),
        ];

        if (self::canViewCostBreakdown()) {
            $lines[] = 'Baza / marża / podatki: '
                .MoneyFormatter::format((float) $summary['base_pln'], 'PLN').' / '
                .MoneyFormatter::format((float) $summary['markup_pln'], 'PLN').' / '
                .MoneyFormatter::format((float) $summary['tax_pln'], 'PLN');
        }

        $lines[] = 'Płacących: '.(int) $summary['paying'].' · opiekunów: '.(int) $summary['gratis'];

        if (! empty($summary['nearest'])) {
            $near = collect($summary['nearest'])
                ->map(fn (array $n): string => $n['qty'].'+'.$n['gratis'].' → '.$n['label'])
                ->implode('; ');
            $lines[] = 'Cennik szablonu (grupa niższa / wyższa): '.$near;
        }

        return implode("\n", $lines);
    }

    public static function canViewCostBreakdown(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole(['admin', 'super_admin']);
    }

    private static function applyKmOverride(Event $record, string $attribute, mixed $formValue, mixed $originalValue): void
    {
        if ($formValue === null || $formValue === '') {
            return;
        }

        $formFloat = (float) $formValue;
        $originalFloat = (float) ($originalValue ?? 0);

        // Nie nadpisuj realnych km zerem z default(0) pola formularza.
        if ($formFloat <= 0.0 && $originalFloat > 0.0) {
            return;
        }

        $record->setAttribute($attribute, $formValue);
    }
}
