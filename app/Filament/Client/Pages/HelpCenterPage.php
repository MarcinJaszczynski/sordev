<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use App\Filament\Concerns\InteractsWithHelpCenter;
use App\Http\Middleware\ClientPreviewMiddleware;
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
        return 'Krótkie instrukcje: płatności, umowa, faktura i lista uczestników.';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return true;
        }

        return $user->hasRole(['admin', 'super_admin', 'biuro']) && ClientPreviewMiddleware::isActive();
    }

    protected function helpPanel(): string
    {
        return HelpCatalog::PANEL_PORTAL;
    }
}
