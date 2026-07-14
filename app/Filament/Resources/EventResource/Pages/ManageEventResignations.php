<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource\RelationManagers\ResignationsRelationManager;
use Filament\Resources\Pages\Concerns\HasRelationManagers;

class ManageEventResignations extends ManageEventParticipantsSection
{
    use HasRelationManagers;

    protected static string $view = 'filament.resources.event-resource.pages.event-participants-relation-managers';

    protected static ?string $navigationLabel = 'Rezygnacje';

    protected static ?string $title = 'Rezygnacje uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            ResignationsRelationManager::class,
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
