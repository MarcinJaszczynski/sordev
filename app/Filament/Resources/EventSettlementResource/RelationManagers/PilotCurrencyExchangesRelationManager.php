<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Models\Currency;
use App\Services\PilotSettlementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PilotCurrencyExchangesRelationManager extends RelationManager
{
    protected static string $relationship = 'currencyExchanges';

    protected static ?string $title = 'Wymiany walut pilota';

    public function form(Form $form): Form
    {
        $syncFromAmount = function (Forms\Set $set, Forms\Get $get): void {
            $to = (float) ($get('to_amount') ?? 0);
            $rate = (float) ($get('exchange_rate') ?? 0);
            if ($to > 0 && $rate > 0) {
                $set('from_amount', round($to * $rate, 2));
            }
        };

        return $form->schema([
            Forms\Components\Select::make('from_currency_id')
                ->label('Z waluty')
                ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->live(),

            Forms\Components\Select::make('to_currency_id')
                ->label('Na walutę')
                ->options(fn () => Currency::orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->different('from_currency_id')
                ->live(),

            Forms\Components\TextInput::make('to_amount')
                ->label('Kwota otrzymana')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->live(onBlur: true)
                ->afterStateUpdated($syncFromAmount),

            Forms\Components\TextInput::make('exchange_rate')
                ->label('Kurs wymiany')
                ->helperText('Ile waluty źródłowej za 1 jednostkę docelową (np. PLN→EUR: 4,30). Kwota oddana = otrzymana × kurs.')
                ->numeric()
                ->step(0.00001)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($syncFromAmount),

            Forms\Components\TextInput::make('from_amount')
                ->label('Kwota oddana')
                ->helperText('Przy podanym kursie wyliczana automatycznie (otrzymana × kurs).')
                ->numeric()
                ->required()
                ->minValue(0.01),

            Forms\Components\TextInput::make('rate_difference_pln')
                ->label('Różnica kursowa (PLN)')
                ->numeric()
                ->default(0)
                ->helperText('Opcjonalnie — różnica względem kursu biura.'),

            Forms\Components\DateTimePicker::make('exchanged_at')
                ->label('Data wymiany')
                ->default(now())
                ->required(),

            Forms\Components\Textarea::make('notes')
                ->label('Uwagi')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeExchangeFormData(array $data): array
    {
        [$from, $to, $rate] = PilotSettlementService::normalizeCurrencyExchangeAmounts(
            $data['from_amount'] ?? 0,
            $data['to_amount'] ?? 0,
            $data['exchange_rate'] ?? null,
        );

        $data['from_amount'] = $from;
        $data['to_amount'] = $to;
        $data['exchange_rate'] = $rate;

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exchanged_at')->label('Data')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('fromCurrency.symbol')->label('Z'),
                Tables\Columns\TextColumn::make('from_amount')->label('Kwota'),
                Tables\Columns\TextColumn::make('toCurrency.symbol')->label('Na'),
                Tables\Columns\TextColumn::make('to_amount')->label('Otrzymano'),
                Tables\Columns\TextColumn::make('exchange_rate')->label('Kurs'),
                Tables\Columns\TextColumn::make('rate_difference_pln')->label('Różnica PLN'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj wymianę')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data = $this->normalizeExchangeFormData($data);
                        $data['created_by'] = Auth::id();

                        return $data;
                    })
                    ->after(function (): void {
                        $this->getOwnerRecord()->recalculatePilotCash();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->normalizeExchangeFormData($data))
                    ->after(fn () => $this->getOwnerRecord()->recalculatePilotCash()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->getOwnerRecord()->recalculatePilotCash()),
            ])
            ->emptyStateHeading('Brak wymian walut')
            ->emptyStateDescription('Wymiana waluty nie jest wydatkiem — wpływa na saldo gotówki pilota per waluta.');
    }
}
