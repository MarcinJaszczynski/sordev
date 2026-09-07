<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventDocumentsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\AgreementsRelationManager;
use App\Filament\Resources\EventResource\RelationManagers\ContractsRelationManager;
use App\Models\Contract;
use App\Models\EventAgreement;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class ManageEventContracts extends SingleRelationManagerPage
{
    use HasEventDocumentsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-documents-relation-managers';

    protected static ?string $navigationLabel = 'Umowy';

    /** H1 = aktywna sekcja nested (primary: Dokumenty → Umowy). */
    protected static ?string $title = 'Umowy';

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        // Nested w module Dokumenty — primary landing to ManageEventDocuments (Pliki).
        return false;
    }

    /**
     * Kanon: zawsze Contracts gdy tabela istnieje.
     * Agreements tylko na środowiskach bez migracji `contracts`.
     */
    protected static function relationManager(): string
    {
        return Schema::hasTable('contracts')
            ? ContractsRelationManager::class
            : AgreementsRelationManager::class;
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            HelpArticleAction::make('umowy'),
        ];

        if (! Schema::hasTable('contracts') || ! Schema::hasTable('event_agreements')) {
            return $actions;
        }

        $pending = $this->pendingLegacyAgreementsCount();
        if ($pending === 0) {
            return $actions;
        }

        $eventId = (int) $this->getRecord()->getKey();

        $actions[] = Action::make('migrate_legacy_agreements')
            ->label("Przenieś stare umowy tej imprezy ({$pending})")
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->tooltip('Jednorazowa migracja starych umów do nowego modułu umów.')
            ->requiresConfirmation()
            ->modalHeading('Przeniesienie starych umów')
            ->modalDescription("Przeniesie {$pending} starych umów wyłącznie tej imprezy (bezpiecznie, bez duplikatów). Nowe umowy twórz już tylko w module Umowy.")
            ->action(function () use ($eventId): void {
                Artisan::call('contracts:migrate-from-event-agreements', [
                    '--event' => $eventId,
                ]);
                Notification::make()
                    ->title('Przenoszenie zakończone')
                    ->body(trim(Artisan::output()) ?: 'Stare umowy tej imprezy przeniesione do nowego modułu.')
                    ->success()
                    ->send();
            });

        return $actions;
    }

    protected function pendingLegacyAgreementsCount(): int
    {
        $eventId = (int) $this->getRecord()->getKey();

        $migratedIds = Contract::query()
            ->where('event_id', $eventId)
            ->whereNotNull('legacy_event_agreement_id')
            ->pluck('legacy_event_agreement_id');

        return EventAgreement::query()
            ->where('event_id', $eventId)
            ->whereNotIn('id', $migratedIds)
            ->count();
    }
}
