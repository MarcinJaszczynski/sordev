<?php

namespace App\Filament\Resources\CurrencyResource\Pages;

use App\Filament\Resources\CurrencyResource;
use App\Services\CurrencyRateService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListCurrencies extends ListRecords
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Dodaj walutę'),
            Actions\Action::make('updateRates')
                ->label('Aktualizuj kursy NBP')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aktualizuj kursy walut')
                ->modalDescription('Czy na pewno chcesz pobrać aktualne kursy z NBP i przeliczić wszystkie szablony imprez?')
                ->modalSubmitActionLabel('Tak, aktualizuj')
                ->action(function () {
                    try {
                        $service = app(CurrencyRateService::class);
                        $updatedCount = $service->updateRates();
                        
                        Notification::make()
                            ->title('Kursy zostały zaktualizowane')
                            ->body("Zaktualizowano {$updatedCount} walut i przeliczono szablony imprez")
                            ->success()
                            ->duration(5000)
                            ->send();
                            
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Błąd aktualizacji kursów')
                            ->body('Szczegóły: ' . $e->getMessage())
                            ->danger()
                            ->duration(8000)
                            ->send();
                    }
                }),
        ];
    }
}
