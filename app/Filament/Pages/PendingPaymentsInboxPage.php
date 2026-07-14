<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class PendingPaymentsInboxPage extends Page
{
    use AuthorizesVendorInvoices;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.pending-payments-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Sterta płatności';

    protected static ?int $navigationSort = 6;

    public string $displayMode = 'list';

    public string $typeFilter = 'all';

    public string $payerFilter = 'all';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || static::canViewInvoices());
    }

    public function getTitle(): string
    {
        return 'Sterta płatności';
    }

    public function getNavigationTabs(): array
    {
        return FinanceModuleNavigation::tabs('pending-payments');
    }

    public function markCompleted(string $rowId): void
    {
        app(\App\Services\PendingPaymentCompletionService::class)->complete($rowId);

        unset($this->inboxEntries, $this->calendarEvents);

        Notification::make()
            ->title('Oznaczono jako wykonane')
            ->success()
            ->send();
    }

    #[Computed]
    public function inboxEntries(): array
    {
        $entries = app(\App\Services\PendingPaymentAggregator::class)->collect();

        if ($this->typeFilter !== 'all') {
            $entries = $entries->where('type', $this->typeFilter);
        }

        if ($this->payerFilter !== 'all') {
            $entries = $entries->where('paid_by', $this->payerFilter);
        }

        return $entries->values()->all();
    }

    #[Computed]
    public function calendarEvents(): array
    {
        return collect($this->inboxEntries)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'title' => ($row['event_code'] ? $row['event_code'].' · ' : '').$row['title'],
                'start' => $row['due_date'],
                'backgroundColor' => $row['color'],
                'borderColor' => $row['color'],
                'url' => $row['url'],
            ])
            ->all();
    }
}
