<?php

namespace App\Filament\Forms;

use App\Models\Currency;
use App\Models\Event;
use App\Services\EventManualPricePerPersonService;
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
                ->columns(2)
                ->columnSpanFull()
                ->hiddenOn('create')
                ->schema([
                    Forms\Components\Placeholder::make('calculated_price_per_person_preview')
                        ->label('Cena z kalkulacji (do zapłaty)')
                        ->content(function (?Event $record, Get $get): string {
                            if (! $record) {
                                return '—';
                            }

                            $count = max(1, (int) ($get('participant_count') ?? $record->participant_count ?? 1));
                            $preview = self::previewEventFromFormState($record, $get);
                            $calc = app(EventManualPricePerPersonService::class)->calculatedForEvent($preview, $count);

                            if (! $calc) {
                                return 'Brak danych kalkulacji';
                            }

                            $paying = (int) ($calc['paying'] ?? $count);
                            $rounded = MoneyFormatter::format((float) ($calc['price_per_person_rounded'] ?? 0), 'PLN');
                            $exact = MoneyFormatter::format((float) ($calc['price_per_person'] ?? 0), 'PLN');

                            return "Zaokrąglona: {$rounded}\nDokładna: {$exact}\nPłacących: {$paying}";
                        })
                        ->helperText('Wyliczona z kalkulacji (baza + marża + podatki) ÷ liczba płacących uczestników. Uwzględnia autokar imprezy oraz bieżące km transferu/programu.')
                        ->extraAttributes(['class' => 'whitespace-pre-line']),

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

                            // Załaduj waluty do mapowania ID → code
                            $currencies = Currency::query()
                                ->whereIn('id', array_column($formLines, 'currency_id'))
                                ->get()
                                ->keyBy('id');

                            // Konwertuj format dla formatLinesLabel
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
                        ->visible(fn (Get $get): bool => (bool) $get('use_manual_price_per_person')),

                    Forms\Components\Toggle::make('use_manual_price_per_person')
                        ->label('Zablokuj auto-przeliczanie — ustal ręcznie cenę za płacącego')
                        ->default(false)
                        ->live()
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
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Dodaj walutę')
                        ->visible(fn (Get $get): bool => (bool) $get('use_manual_price_per_person'))
                        ->required(fn (Get $get): bool => (bool) $get('use_manual_price_per_person'))
                        ->minItems(1)
                        ->columnSpanFull()
                        ->afterStateUpdated(fn ($livewire) => method_exists($livewire, 'dispatch')
                            ? $livewire->dispatch('event-price-table-refresh')
                            : null)
                        ->helperText('Każda waluta tylko raz. Dotyczy uczestników płacących (bez '.EventParticipantGroupLabels::GRATIS_GENITIVE.', pilota i obsługi).'),
                ]),
        ];
    }

    /**
     * Kopia imprezy z polami cenotwórczymi z formularza — do live-podglądu kalkulacji.
     * Relacje (program, qty) pozostają z oryginału; autokar / km / miejsce startu z $get.
     */
    protected static function previewEventFromFormState(Event $record, Get $get): Event
    {
        $preview = $record->newInstance();
        $preview->forceFill($record->getAttributes());
        $preview->exists = true;
        $preview->syncOriginal();

        foreach (['start_place_id', 'bus_id', 'participant_count'] as $intField) {
            if ($get($intField) !== null && $get($intField) !== '') {
                $preview->{$intField} = (int) $get($intField) ?: null;
            }
        }

        foreach (['transfer_km', 'program_km', 'manual_transport_cost'] as $floatField) {
            if ($get($floatField) !== null && $get($floatField) !== '') {
                $preview->{$floatField} = (float) $get($floatField);
            }
        }

        if ($get('use_manual_transport_cost') !== null) {
            $preview->use_manual_transport_cost = (bool) $get('use_manual_transport_cost');
        }

        if ((int) ($preview->bus_id ?? 0) !== (int) ($record->bus_id ?? 0)) {
            $preview->unsetRelation('bus');
        } elseif ($record->relationLoaded('bus')) {
            $preview->setRelation('bus', $record->getRelation('bus'));
        }

        return $preview;
    }
}
