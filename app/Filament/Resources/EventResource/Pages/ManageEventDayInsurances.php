<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Forms\EventReadinessFields;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Illuminate\Database\Eloquent\Model;

class ManageEventDayInsurances extends EditRecord
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;
    use HasRelationManagers;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-operations-insurances';

    protected static ?string $navigationLabel = 'Ubezpieczenia';

    protected static ?string $title = 'Ubezpieczenia';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Polisa imprezy / gotowość')
                ->description('Status „Gotowe” oznacza gotowość imprezy po stronie ubezpieczeń. Plan / wpłaty / plik dla NNW i KL: klik w wiersz tabeli (ten sam panel co w Finansach → Koszty).')
                ->icon('heroicon-o-shield-check')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Toggle::make('insurance_event_ready')
                        ->label('Gotowość imprezy — ubezpieczenie OK')
                        ->helperText('Zaznacz, gdy polisa jest domknięta (ustawia status „Gotowe”).')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, ?Model $record): void {
                            $component->state(($record?->insurance_status ?? 'pending') === 'completed');
                        })
                        ->afterStateUpdated(function (bool $state, Set $set): void {
                            $set('insurance_status', $state ? 'completed' : 'pending');
                        })
                        ->columnSpanFull(),
                    ...EventReadinessFields::insuranceModalSchema(),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, EventReadinessFields::insuranceFormState($this->record));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        EventReadinessFields::persistInsurance($record, $data);

        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zapisano polisę imprezy';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [DayInsurancesRelationManager::class];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }
}
