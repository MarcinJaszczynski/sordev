<?php

namespace App\Filament\Resources\EventSettlementResource\RelationManagers;

use App\Models\Currency;
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

            Forms\Components\TextInput::make('from_amount')
                ->label('Kwota wydana')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->live(onBlur: true),

            Forms\Components\TextInput::make('exchange_rate')
                ->label('Kurs wymiany')
                ->numeric()
                ->step(0.00001)
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                    $from = (float) ($get('from_amount') ?? 0);
                    $rate = (float) ($state ?? 0);
                    if ($from > 0 && $rate > 0) {
                        $set('to_amount', round($from * $rate, 2));
                    }
                }),

            Forms\Components\TextInput::make('to_amount')
                ->label('Kwota otrzymana')
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
        ])->columns(2);
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
                        $data['created_by'] = Auth::id();

                        return $data;
                    })
                    ->after(function (): void {
                        $this->getOwnerRecord()->recalculatePilotCash();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->getOwnerRecord()->recalculatePilotCash()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->getOwnerRecord()->recalculatePilotCash()),
            ])
            ->emptyStateHeading('Brak wymian walut')
            ->emptyStateDescription('Wymiana waluty nie jest wydatkiem — wpływa na saldo gotówki pilota per waluta.');
    }
}
