<?php

namespace App\Filament\Widgets;

use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;

class SitemapGeneratorWidget extends Widget
{
    protected static ?string $maxContentWidth = '4xl';

    public function generateSitemap(): void
    {
        try {
            Artisan::call('sitemap:generate');

            Notification::make()
                ->title('Sukces!')
                ->body('Sitemap został pomyślnie wygenerowany!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Błąd!')
                ->body('Błąd: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        return view('filament.widgets.sitemap-generator-widget');
    }
}
