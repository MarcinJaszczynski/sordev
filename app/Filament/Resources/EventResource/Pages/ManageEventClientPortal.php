<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Client\Resources\ClientEventResource;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Schema;

class ManageEventClientPortal extends ManageEventParticipantsSection
{
    protected static string $view = 'filament.resources.event-resource.pages.manage-event-client-portal';

    protected static ?string $navigationLabel = 'Portal klienta';

    protected static ?string $title = 'Portal klienta';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podgląd portalu klienta')
                    ->icon('heroicon-o-eye')
                    ->description('Otwórz widok imprezy tak, jak zobaczy go klient lub opiekun grupy.')
                    ->schema([
                        Forms\Components\Placeholder::make('client_preview_link')
                            ->hiddenLabel()
                            ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                                '<a href="'.e(ClientEventResource::getUrl('index', panel: 'portal').'?preview=1').'" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">'
                                .'<span>Podgląd portalu klienta</span>'
                                .'</a>'
                            )),
                    ]),
                Forms\Components\Section::make('Dostępy klientów')
                    ->description('Zaproś uczestników i opiekunów grupy. Dostęp może też powstać automatycznie po podpisaniu umowy online.')
                    ->visible(fn (): bool => Schema::hasTable('event_portal_accesses'))
                    ->schema([
                        Forms\Components\ViewField::make('client_portal_toolbar')
                            ->view('filament.resources.event-resource.components.client-portal-toolbar-field'),
                    ]),
                Forms\Components\Section::make('Wnioski o fakturę')
                    ->visible(fn (): bool => Schema::hasTable('client_invoice_requests'))
                    ->schema([
                        Forms\Components\ViewField::make('client_invoice_requests')
                            ->view('filament.resources.event-resource.components.client-invoice-requests-field'),
                    ]),
            ])
            ->statePath('data');
    }
}
