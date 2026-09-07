<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Event;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Ręczna migawka stanu imprezy (program + koszty) — punkt odniesienia przed zmianami.
 */
final class CreateEventSnapshotAction
{
    public static function make(string $name = 'create_snapshot'): Action
    {
        return Action::make($name)
            ->label('Zapisz migawkę')
            ->icon('heroicon-o-camera')
            ->color('gray')
            ->tooltip('Zapisuje stan imprezy (program i koszty) na dany moment — np. przed zmianami od klienta.')
            ->modalHeading('Zapisz migawkę imprezy')
            ->modalSubmitActionLabel('Zapisz')
            ->form([
                TextInput::make('name')
                    ->label('Nazwa migawki')
                    ->required()
                    ->maxLength(255)
                    ->default(fn (): string => 'Stan przed zmianami klienta '.now()->format('d.m.Y')),
                Textarea::make('description')
                    ->label('Opis')
                    ->rows(3)
                    ->maxLength(500)
                    ->helperText('Opcjonalnie: kontekst (np. klient zmieni liczbę osób / program).'),
            ])
            ->action(function (array $data, $livewire): void {
                /** @var Event $event */
                $event = method_exists($livewire, 'getRecord')
                    ? $livewire->getRecord()
                    : $livewire->record;

                $event->createManualSnapshot(
                    $data['name'],
                    filled($data['description'] ?? null) ? (string) $data['description'] : null,
                );

                Notification::make()
                    ->title('Migawka zapisana')
                    ->success()
                    ->send();
            });
    }
}
