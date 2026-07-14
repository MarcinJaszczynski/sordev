<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource\Concerns\ResolvesEventSettlement;
use App\Filament\Resources\EventSettlementResource\RelationManagers\ParticipantPaymentsRelationManager;
use Filament\Resources\Pages\Concerns\HasRelationManagers;

class ManageEventSettlementPayments extends ManageEventParticipantsSection
{
    use HasRelationManagers;
    use ResolvesEventSettlement;

    protected static string $view = 'filament.resources.event-resource.pages.event-participants-payments-section';

    protected static ?string $navigationLabel = 'Wpłaty uczestników';

    protected static ?string $title = 'Wpłaty uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->resolveEventSettlement($this->getRecord());
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ParticipantPaymentsRelationManager::class,
        ];
    }

    public function getRelationManagers(): array
    {
        $managers = [];

        foreach ($this->getAllRelationManagers() as $manager) {
            $managers[$manager] = $manager;
        }

        return $managers;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }
}
