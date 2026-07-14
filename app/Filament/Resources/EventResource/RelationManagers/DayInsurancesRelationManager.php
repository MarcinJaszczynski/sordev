<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventDayInsurance;
use App\Support\MoneyFormatter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DayInsurancesRelationManager extends RelationManager
{
    protected static string $relationship = 'dayInsurances';

    protected static ?string $recordTitleAttribute = 'day';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('day')->label('Dzień')->numeric()->required(),
            Select::make('insurance_id')->label('Ubezpieczenie')->relationship('insurance', 'name')->preload()->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day')->label('Dzień'),
                Tables\Columns\TextColumn::make('insurance.name')->label('Ubezpieczenie'),
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Szac. koszt')
                    ->state(function (EventDayInsurance $record): string {
                        $insurance = $record->insurance;
                        $event = $record->event ?? $this->getOwnerRecord();
                        $participants = max(1, (int) ($event->participant_count ?? 1));
                        $amount = (float) ($insurance?->price_per_person ?? 0) * $participants;

                        return $amount > 0
                            ? MoneyFormatter::format($amount, 'PLN')
                            : '—';
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ]);
    }

    private function syncSettlementFromEvent(): void
    {
        $this->getOwnerRecord()->refreshActiveSettlementCosts();
    }
}
