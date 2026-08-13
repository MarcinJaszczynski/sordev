<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\EventResource\Concerns\InteractsWithSettlementCostDrawer;
use App\Filament\Resources\EventResource\Concerns\ManagesDayInsuranceSettlementFinance;
use App\Models\EventDayInsurance;
use App\Models\Insurance;
use App\Services\InsuranceCostCalculator;
use App\Support\MoneyFormatter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Livewire\WithFileUploads;

class DayInsurancesRelationManager extends RelationManager
{
    use InteractsWithSettlementCostDrawer;
    use ManagesDayInsuranceSettlementFinance;
    use WithFileUploads;

    protected static string $relationship = 'dayInsurances';

    protected static ?string $recordTitleAttribute = 'day';

    protected static ?string $title = 'Ubezpieczenia dzienne (kosztorys)';

    protected static string $view = 'filament.resources.event-resource.relation-managers.day-insurances';

    public function mount(): void
    {
        parent::mount();
        $this->initializeSettlementCostDrawerForms();
    }

    protected function invalidateSettlementCostCaches(): void
    {
        unset($this->selectedRow);
        $this->resetTable();
    }

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([
            TextInput::make('day')->label('Dzień')->numeric()->required(),
            Select::make('insurance_id')
                ->label('Ubezpieczenie')
                ->relationship(
                    name: 'insurance',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                )
                ->getOptionLabelFromRecordUsing(function (Insurance $record): string {
                    $type = Insurance::coverageTypeLabel($record->coverage_type);

                    return $type ? "{$record->name} ({$type})" : (string) $record->name;
                })
                ->preload()
                ->required(),
            Toggle::make('is_done')
                ->label('Zrobione (dzień)')
                ->helperText('Operacyjnie domknięte na dniu — nie księguje wpłaty automatycznie.')
                ->default(false)
                ->visible(fn (): bool => Schema::hasColumn('event_day_insurance', 'is_done')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day')->label('Dzień')->sortable(),
                Tables\Columns\TextColumn::make('insurance.name')->label('Ubezpieczenie'),
                Tables\Columns\TextColumn::make('insurance.coverage_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Insurance::coverageTypeLabel($state) ?? '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'nnw' => 'info',
                        'kl' => 'success',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => Schema::hasColumn('insurances', 'coverage_type')),
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Kalkulacja')
                    ->tooltip('Koszt z gratisami (jak w ofercie); cena/os. klienta = suma ÷ płacący.')
                    ->state(function (EventDayInsurance $record): string {
                        $event = $record->event ?? $this->getOwnerRecord();
                        $paying = max(1, (int) ($event->participant_count ?? 1));
                        $gratis = $event->resolveGratisCountForParticipantCount($paying);
                        $amount = InsuranceCostCalculator::dayAssignmentCost(
                            $record->insurance,
                            $paying,
                            $gratis
                        );

                        return $amount > 0
                            ? MoneyFormatter::format($amount, 'PLN')
                            : '—';
                    }),
                ...$this->dayInsuranceFinanceTableColumns(),
                Tables\Columns\ToggleColumn::make('is_done')
                    ->label('Zrobione')
                    ->visible(fn (): bool => Schema::hasColumn('event_day_insurance', 'is_done'))
                    ->afterStateUpdated(fn () => $this->syncSettlementFromEvent()),
            ])
            ->recordAction('open_finance')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ])
            ->actions([
                ...$this->dayInsuranceFinanceTableActions(),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->syncSettlementFromEvent()),
            ]);
    }

    private function syncSettlementFromEvent(): void
    {
        $this->getOwnerRecord()->refreshActiveSettlementCosts();
    }
}
