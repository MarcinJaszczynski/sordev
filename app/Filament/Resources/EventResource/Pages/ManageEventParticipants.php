<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Forms\EventReadinessFields;
use App\Filament\Resources\EventResource;
use Filament\Actions;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class ManageEventParticipants extends ManageEventParticipantsSection
{
    protected static string $view = 'filament.resources.event-resource.pages.manage-event-participants';

    protected static ?string $navigationLabel = 'Uczestnicy';

    /** H1 = aktywna sekcja nested (primary: Uczestnicy). */
    protected static ?string $title = 'Lista';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return Schema::hasTable('event_participants');
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => collect(static::participantsRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('participants', $urlParameters)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ops_lists')
                ->label('Listy operacyjne')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->tooltip('Eksport list: autokarowa, ubezpieczeniowa i pełny spis uczestników.')
                ->modalHeading('Listy z rejestru uczestników')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zamknij')
                ->modalContent(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    '<div class="space-y-2 text-sm">'
                    .'<p>Eksport CSV z aktywnej listy uczestników (jedno źródło danych).</p>'
                    .'<ul class="list-disc space-y-1 pl-5">'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'bus'])).'">Lista autokarowa</a></li>'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'insurance'])).'">Lista ubezpieczeniowa</a></li>'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'roster'])).'">Pełny spis uczestników</a></li>'
                    .'</ul></div>'
                )),

            Actions\Action::make('insurance')
                ->label('Ubezpieczenie')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->tooltip('Polisa, status i dokumenty ubezpieczenia imprezy.')
                ->modalHeading(fn (): string => 'Ubezpieczenie: '.trim(($this->record->code ?: '#'.$this->record->id).' — '.$this->record->name))
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Zapisz')
                ->record($this->record)
                ->form(EventReadinessFields::insuranceSection())
                ->fillForm(fn (): array => EventReadinessFields::insuranceFormState($this->record))
                ->action(function (array $data): void {
                    EventReadinessFields::persistInsurance($this->record, $data);

                    Notification::make()
                        ->title('Zapisano dane ubezpieczenia')
                        ->success()
                        ->send();
                }),
        ];
    }
}
