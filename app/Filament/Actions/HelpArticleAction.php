<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Client\Pages\HelpCenterPage as ClientHelpCenterPage;
use App\Filament\Pages\HelpCenterPage as AdminHelpCenterPage;
use App\Filament\Pilot\Pages\HelpCenterPage as PilotHelpCenterPage;
use App\Support\Help\HelpCatalog;
use Filament\Actions\Action;
use Filament\Facades\Filament;

final class HelpArticleAction
{
    public static function make(string $slug, ?string $panel = null): Action
    {
        $panelId = $panel ?? (Filament::getCurrentPanel()?->getId() ?? HelpCatalog::PANEL_ADMIN);

        return Action::make('help_article')
            ->label('Jak to zrobić?')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->url(fn (): string => self::url($slug, $panelId))
            ->openUrlInNewTab();
    }

    public static function url(string $slug, ?string $panel = null): string
    {
        $panelId = $panel ?? (Filament::getCurrentPanel()?->getId() ?? HelpCatalog::PANEL_ADMIN);

        $base = match ($panelId) {
            HelpCatalog::PANEL_PORTAL, 'portal' => ClientHelpCenterPage::getUrl(
                panel: 'portal',
            ),
            HelpCatalog::PANEL_PILOT, 'pilot' => PilotHelpCenterPage::getUrl(
                panel: 'pilot',
            ),
            default => AdminHelpCenterPage::getUrl(),
        };

        return $base.(str_contains($base, '?') ? '&' : '?').'article='.rawurlencode($slug);
    }
}
