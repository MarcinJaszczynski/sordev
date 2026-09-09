<?php

namespace App\Filament\Pages;

use App\Jobs\RecalculatePlaceDistancesJob;
use App\Models\PlaceDistance;
use App\Services\PlaceDistanceRouteService;
use App\Support\FilamentNavigation;
use App\Support\OpenRouteServiceSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OpenRouteServiceSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static string $view = 'filament.pages.openroute-service-settings';

    protected static ?string $navigationLabel = 'OpenRouteService';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'openrouteservice';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin']);
    }

    public function getTitle(): string
    {
        return 'OpenRouteService — odległości drogowe';
    }

    public function mount(): void
    {
        $status = OpenRouteServiceSettings::status();

        $this->form->fill([
            'api_key' => '',
            'clear_api_key' => false,
            'requests_per_minute' => $status['requests_per_minute'],
            'daily_limit' => $status['daily_limit'],
            'min_interval_ms' => $status['min_interval_ms'],
        ]);
    }

    public function form(Form $form): Form
    {
        $status = OpenRouteServiceSettings::status();

        return $form
            ->schema([
                Forms\Components\TextInput::make('api_key')
                    ->label('Klucz API OpenRouteService')
                    ->password()
                    ->revealable()
                    ->helperText(
                        $status['has_api_key']
                            ? 'Aktualnie: '.$status['api_key_masked'].' (źródło: '.($status['api_key_source'] === 'panel' ? 'panel' : '.env').'). Zostaw puste, żeby nie zmieniać.'
                            : 'Brak klucza — wklej klucz z openrouteservice.org albo ustaw OPENROUTESERVICE_API_KEY w .env.'
                    )
                    ->maxLength(255),
                Forms\Components\Toggle::make('clear_api_key')
                    ->label('Usuń klucz z panelu (wróć do .env / braku)')
                    ->visible(fn (): bool => OpenRouteServiceSettings::status()['api_key_source'] === 'panel'),
                Forms\Components\TextInput::make('requests_per_minute')
                    ->label('Limit requestów / minutę')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(500)
                    ->helperText('Darmowy ORS ≈ 40/min. Zostaw margines (np. 35).'),
                Forms\Components\TextInput::make('daily_limit')
                    ->label('Limit requestów / dzień')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100000)
                    ->helperText('Darmowy ORS ≈ 2000/dzień dla Directions.'),
                Forms\Components\TextInput::make('min_interval_ms')
                    ->label('Minimalny odstęp między requestami (ms)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(60000)
                    ->helperText('Przy 35/min bezpiecznie ≈ 1700 ms.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $payload = $this->form->getState();

        OpenRouteServiceSettings::save([
            'api_key' => $payload['api_key'] ?? null,
            'clear_api_key' => (bool) ($payload['clear_api_key'] ?? false),
            'requests_per_minute' => (int) ($payload['requests_per_minute'] ?? OpenRouteServiceSettings::DEFAULT_REQUESTS_PER_MINUTE),
            'daily_limit' => (int) ($payload['daily_limit'] ?? OpenRouteServiceSettings::DEFAULT_DAILY_LIMIT),
            'min_interval_ms' => (int) ($payload['min_interval_ms'] ?? OpenRouteServiceSettings::DEFAULT_MIN_INTERVAL_MS),
        ]);

        Notification::make()
            ->title('Zapisano ustawienia OpenRouteService')
            ->success()
            ->send();

        $this->mount();
    }

    public function dispatchRecalculateEstimates(): void
    {
        if (! OpenRouteServiceSettings::status()['has_api_key']) {
            Notification::make()->title('Brak klucza API ORS')->danger()->send();

            return;
        }

        RecalculatePlaceDistancesJob::dispatch(force: true, estimatesOnly: true);

        Notification::make()
            ->title('Kolejka: przeliczanie szacunków → trasy drogowe')
            ->body('Job respektuje limity z tej strony. Postęp widać po odświeżeniu listy odległości (kolumna Źródło).')
            ->success()
            ->send();
    }

    public function dispatchRecalculateMissing(): void
    {
        if (! OpenRouteServiceSettings::status()['has_api_key']) {
            Notification::make()->title('Brak klucza API ORS')->danger()->send();

            return;
        }

        RecalculatePlaceDistancesJob::dispatch(force: false, estimatesOnly: false);

        Notification::make()
            ->title('Kolejka: uzupełnianie brakujących odległości ORS')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $routes = app(PlaceDistanceRouteService::class);

        return [
            'status' => OpenRouteServiceSettings::status(),
            'usage' => $routes->usageSnapshot(),
            'sourceStats' => PlaceDistance::sourceStats(),
            'envFallback' => filled(config('services.openrouteservice.key')),
        ];
    }
}
