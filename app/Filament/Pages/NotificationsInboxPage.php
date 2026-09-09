<?php

namespace App\Filament\Pages;

use App\Services\NotificationService;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class NotificationsInboxPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notifications-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EVENTS;

    protected static ?string $navigationLabel = 'Powiadomienia';

    protected static ?int $navigationSort = 2;

    public string $typeFilter = 'all';

    public bool $unreadOnly = false;

    public int $perPage = 25;

    public int $page = 1;

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

        $data = NotificationService::getInboxDataForUser(
            $userId,
            $this->typeFilter === 'all' ? null : $this->typeFilter,
            $this->unreadOnly,
        );

        $items = collect($data['items'] ?? []);
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / max(1, $this->perPage)));

        if ($this->page > $lastPage) {
            $this->page = $lastPage;
        }

        $offset = ($this->page - 1) * $this->perPage;

        $data['items'] = $items->slice($offset, $this->perPage)->values()->all();
        $data['pagination'] = [
            'total' => $total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $this->perPage, $total),
        ];

        return $data;
    }

    public function updatedTypeFilter(): void
    {
        $this->page = 1;
        unset($this->inboxData);
    }

    public function updatedUnreadOnly(): void
    {
        $this->page = 1;
        unset($this->inboxData);
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
        unset($this->inboxData);
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
        unset($this->inboxData);
    }

    #[On('refresh-notifications')]
    public function handleRefreshNotifications(): void
    {
        $this->refreshInbox();
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

    public function markUnread(string $fingerprint): void
    {
        $userId = (int) Auth::id();

        if ($userId <= 0 || $fingerprint === '') {
            return;
        }

        NotificationService::markAsUnread($userId, $fingerprint);
        unset($this->inboxData);

        Notification::make()
            ->title('Oznaczono jako nieprzeczytane')
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
