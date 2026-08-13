<?php

namespace App\Filament\Pages;

use App\Support\DemoMailMode;
use App\Support\FilamentNavigation;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DemoModePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static string $view = 'filament.pages.demo-mode';

    protected static ?string $navigationLabel = 'Tryb demo';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'demo-mode';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin']);
    }

    public function getTitle(): string
    {
        return 'Tryb demo poczty';
    }

    public function mount(): void
    {
        $status = DemoMailMode::status();

        $this->form->fill([
            'enabled' => $status['enabled'],
            'email' => $status['email'] ?: DemoMailMode::DEFAULT_EMAIL,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('enabled')
                    ->label('Włącz tryb demo poczty')
                    ->helperText('Gdy włączone, WSZYSTKIE e-maile z aplikacji (wnioski, zapytania, dostępy itd.) trafiają wyłącznie na poniższy adres — niezależnie od oryginalnego odbiorcy.')
                    ->live(),
                Forms\Components\TextInput::make('email')
                    ->label('Adres e-mail odbiorcy demo')
                    ->email()
                    ->required(fn (Get $get): bool => (bool) $get('enabled'))
                    ->visible(fn (Get $get): bool => (bool) $get('enabled'))
                    ->maxLength(255)
                    ->placeholder(DemoMailMode::DEFAULT_EMAIL)
                    ->helperText('Np. Twój adres Gmail do testów na stagingu / lokalnie.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $payload = $this->form->getState();
        $enabled = (bool) ($payload['enabled'] ?? false);
        $email = $payload['email'] ?? DemoMailMode::DEFAULT_EMAIL;

        DemoMailMode::save($enabled, is_string($email) ? $email : null);

        Notification::make()
            ->title($enabled ? 'Tryb demo włączony' : 'Tryb demo wyłączony')
            ->body($enabled
                ? 'Maile będą przekierowywane na: '.DemoMailMode::email()
                : 'Poczta wraca do normalnych odbiorców (np. rafa@bprafa.pl dla zapytań).')
            ->success()
            ->send();

        $this->mount();
    }

    protected function getViewData(): array
    {
        return [
            'status' => DemoMailMode::status(),
            'envFallback' => config('mail.demo_to'),
        ];
    }
}
