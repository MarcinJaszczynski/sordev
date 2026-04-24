<?php

namespace App\Providers;

use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
use App\Models\Place;
use App\Observers\EventObserver;
use App\Observers\PlaceObserver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('app-styles', Vite::asset('resources/css/app.css')),
            // Keep Filament JS stack isolated. Custom app.js is loaded on front layouts,
            // and injecting it globally into the panel can break table/select Alpine boot.
        ]);

        // Rejestracja komponenty Blade dla powiadomień
        $this->app['blade.compiler']->component('app.filament.components.topbar-notifications', 'app-filament-components-topbar-notifications');

        // Auto-generate place distance pairs on place create/update
        Place::observe(PlaceObserver::class);

        // Przelicz koszty i odśwież rozliczenie przy zmianach danych imprezy
        Event::observe(EventObserver::class);

        // Backward-compatible alias: stara ścieżka komponentu po przeprowadzce klasy
        // (zapobiega ComponentNotFoundException dla starych tokenów Livewire w sesji użytkownika)
        Livewire::component(
            'app.filament.relation-managers.tasks-relation-manager',
            TasksRelationManager::class
        );

        // Po przeniesieniu aplikacji fallback na aktualny host eliminuje linki z 127.0.0.1/localhost.
        if (! $this->app->runningInConsole()) {
            $configuredPublicUrl = (string) config('app.public_url', config('app.url'));
            $configuredHost = (string) (parse_url($configuredPublicUrl, PHP_URL_HOST) ?? '');

            if ($configuredHost !== '' && $this->isLoopbackHost($configuredHost)) {
                $request = request();
                $requestRoot = rtrim($request->getSchemeAndHttpHost(), '/');

                URL::forceRootUrl($requestRoot);

                config([
                    'app.url' => $requestRoot,
                    'app.public_url' => $requestRoot,
                    'filesystems.disks.public.url' => $requestRoot.'/storage',
                ]);
            }
        }
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
