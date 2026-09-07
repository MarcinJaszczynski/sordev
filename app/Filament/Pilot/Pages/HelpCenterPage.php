<?php

declare(strict_types=1);

namespace App\Filament\Pilot\Pages;

use App\Filament\Concerns\InteractsWithHelpCenter;
use App\Services\PilotAccessService;
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
        return 'Skrócone instrukcje dla pilota: program, frekwencja, gotówka i dokumenty.';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['pilot'])) {
            return true;
        }

        return app(PilotAccessService::class)->canStaffPreviewPortal($user);
    }

    protected function helpPanel(): string
    {
        return HelpCatalog::PANEL_PILOT;
    }
}
