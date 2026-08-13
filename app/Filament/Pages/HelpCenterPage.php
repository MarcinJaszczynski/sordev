<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithHelpCenter;
use App\Support\Help\HelpCatalog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class HelpCenterPage extends Page
{
    use InteractsWithHelpCenter;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string $view = 'filament.pages.help-center';

    protected static ?string $navigationLabel = 'Pomoc';

    protected static ?string $title = 'Pomoc';

    protected static ?string $slug = 'help';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 999;

    public function mount(): void
    {
        $this->mountInteractsWithHelpCenter();
    }

    public function getSubheading(): ?string
    {
        return 'Scenariusze pracy biura — jak wykonać typowe flow krok po kroku.';
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    protected function helpPanel(): string
    {
        return HelpCatalog::PANEL_ADMIN;
    }
}
