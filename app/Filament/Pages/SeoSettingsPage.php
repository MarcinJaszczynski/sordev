<?php

namespace App\Filament\Pages;

use App\Models\SeoSetting;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SeoSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string $view = 'filament.pages.seo-settings';

    protected static ?string $navigationLabel = 'Ustawienia SEO';

    protected static ?string $title = 'Ustawienia SEO';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 40;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SeoSetting::organization());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Meta domyślne')
                    ->schema([
                        Forms\Components\TextInput::make('default_title')
                            ->label('Domyślny tytuł strony')
                            ->maxLength(70)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('default_description')
                            ->label('Domyślny opis meta')
                            ->rows(3)
                            ->maxLength(350)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('og_image')
                            ->label('Domyślny obraz OG (ścieżka w public/)')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Organizacja (Schema.org / O nas)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Nazwa'),
                        Forms\Components\TextInput::make('legal_name')->label('Nazwa prawna'),
                        Forms\Components\TextInput::make('phone')->label('Telefon'),
                        Forms\Components\TextInput::make('email')->label('E-mail'),
                        Forms\Components\TextInput::make('street')->label('Ulica'),
                        Forms\Components\TextInput::make('city')->label('Miasto'),
                        Forms\Components\TextInput::make('postal_code')->label('Kod pocztowy'),
                        Forms\Components\TextInput::make('nip')->label('NIP'),
                        Forms\Components\TextInput::make('regon')->label('REGON'),
                        Forms\Components\TextInput::make('license_number')->label('Numer licencji'),
                        Forms\Components\TextInput::make('founded_year')->label('Rok założenia')->numeric(),
                        Forms\Components\TextInput::make('trips_count')->label('Liczba wyjazdów (social proof)')->numeric(),
                        Forms\Components\TextInput::make('schools_count')->label('Liczba szkół/firm (social proof)')->numeric(),
                        Forms\Components\Textarea::make('tfg_info')->label('Informacja TFG')->rows(2)->columnSpanFull(),
                        Forms\Components\TextInput::make('facebook')->label('Facebook URL')->columnSpanFull(),
                        Forms\Components\TextInput::make('instagram')->label('Instagram URL')->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SeoSetting::setValue('organization', $this->form->getState());

        Notification::make()
            ->title('Ustawienia SEO zapisane')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
