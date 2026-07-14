<?php

namespace App\Filament\Pages;

use App\Services\NotificationService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class NotificationsInboxPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notifications-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?string $navigationLabel = 'Powiadomienia';

    protected static ?int $navigationSort = 2;

    public string $typeFilter = 'all';

    public bool $unreadOnly = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user();
    }

    public function getTitle(): string
    {
        return 'Centrum powiadomień';
    }

    #[Computed]
    public function inboxData(): array
    {
        $userId = (int) Auth::id();

        return NotificationService::getInboxDataForUser(
            $userId,
            $this->typeFilter === 'all' ? null : $this->typeFilter,
            $this->unreadOnly,
        );
    }

    public function updatedTypeFilter(): void
    {
        unset($this->inboxData);
    }

    public function updatedUnreadOnly(): void
    {
        unset($this->inboxData);
    }

    public function markRead(string $fingerprint): void
    {
        $userId = (int) Auth::id();

        if ($userId <= 0 || $fingerprint === '') {
            return;
        }

        NotificationService::markAsRead($userId, $fingerprint);
        unset($this->inboxData);

        Notification::make()
            ->title('Oznaczono jako przeczytane')
            ->success()
            ->send();
    }

    public function markAllRead(): void
    {
        $userId = (int) Auth::id();

        if ($userId <= 0) {
            return;
        }

        $marked = NotificationService::markAllAsRead($userId);
        unset($this->inboxData);

        Notification::make()
            ->title($marked > 0 ? "Oznaczono {$marked} powiadomień jako przeczytane" : 'Brak nieprzeczytanych powiadomień')
            ->success()
            ->send();
    }

    public function refreshInbox(): void
    {
        $userId = (int) Auth::id();

        if ($userId > 0) {
            NotificationService::clearCacheForUser($userId);
        }

        unset($this->inboxData);
    }
}
