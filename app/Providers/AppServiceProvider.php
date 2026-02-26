<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Js;
use Filament\Support\Assets\Css;
use Illuminate\Support\Facades\Vite;
use App\Models\Place;
use App\Observers\PlaceObserver;

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
            Js::make('app-scripts', Vite::asset('resources/js/app.js'))->module(), // Dodaj ->module()
        ]);
        
        // Rejestracja komponenty Blade dla powiadomień
        $this->app['blade.compiler']->component('app.filament.components.topbar-notifications', 'app-filament-components-topbar-notifications');

        // Auto-generate place distance pairs on place create/update
        Place::observe(PlaceObserver::class);
    }
}
