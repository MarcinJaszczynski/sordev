<?php

namespace App\Filament\Concerns;

use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;

/**
 * Strona workspace z jednym relation managerem (bez formularza głównego).
 */
abstract class SingleRelationManagerPage extends Page
{
    use HasRelationManagers;
    use InteractsWithRecord;

    protected static string $view = 'filament.pages.single-relation-manager';

    /** @return class-string<RelationManager> */
    abstract protected static function relationManager(): string;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [static::relationManager()];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }
}
