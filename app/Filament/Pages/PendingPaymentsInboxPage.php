<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesVendorInvoices;
use App\Services\PendingPaymentAggregator;
use App\Support\FilamentNavigation;
use App\Support\FinanceModuleNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class PendingPaymentsInboxPage extends Page
{
    use AuthorizesVendorInvoices;

    private const SESSION_KEY = 'pending_payments_inbox';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.pending-payments-inbox';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_FINANCE;

    protected static ?string $navigationLabel = 'Sterta płatności';

    protected static ?int $navigationSort = 6;

    public string $displayMode = 'list';

    public string $typeFilter = 'all';

    public string $payerFilter = 'all';

    /** @var array{entries: array<int, array<string, mixed>>, truncated: bool}|null */
    protected ?array $aggregatedInboxCache = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole(['admin', 'super_admin', 'ksiegowosc']) || static::canViewInvoices());
    }

    public function mount(): void
    {
        $saved = session(self::SESSION_KEY, []);

        if (is_array($saved)) {
            $this->displayMode = in_array($saved['displayMode'] ?? null, ['list', 'calendar'], true)
                ? $saved['displayMode']
                : 'list';
            $this->typeFilter = is_string($saved['typeFilter'] ?? null) ? $saved['typeFilter'] : 'all';
            $this->payerFilter = is_string($saved['payerFilter'] ?? null) ? $saved['payerFilter'] : 'all';
        }
    }

    public function updatedDisplayMode(string $value): void
    {
        $this->persistFilters();
    }

    public function updatedTypeFilter(string $value): void
    {
        $this->persistFilters();
        $this->forgetInboxCache();
    }

    public function updatedPayerFilter(string $value): void
    {
        $this->persistFilters();
        $this->forgetInboxCache();
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

        $this->forgetInboxCache();

        Notification::make()
            ->title('Oznaczono jako wykonane')
            ->success()
            ->send();
    }

    #[Computed]
    public function wasTruncated(): bool
    {
        return (bool) ($this->aggregatedInbox()['truncated'] ?? false);
    }

    #[Computed]
    public function inboxEntries(): array
    {
        return $this->aggregatedInbox()['entries'];
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

    /**
     * @return array{entries: array<int, array<string, mixed>>, truncated: bool}
     */
    protected function aggregatedInbox(): array
    {
        if ($this->aggregatedInboxCache !== null) {
            return $this->aggregatedInboxCache;
        }

        $aggregator = app(PendingPaymentAggregator::class);
        $entries = $aggregator->collect();

        if ($this->typeFilter !== 'all') {
            $entries = $entries->where('type', $this->typeFilter);
        }

        if ($this->payerFilter !== 'all') {
            $entries = $entries->where('paid_by', $this->payerFilter);
        }

        return $this->aggregatedInboxCache = [
            'entries' => $entries->values()->all(),
            'truncated' => $aggregator->wasTruncated(),
        ];
    }

    protected function forgetInboxCache(): void
    {
        $this->aggregatedInboxCache = null;
        unset($this->inboxEntries, $this->calendarEvents, $this->wasTruncated);
    }

    protected function persistFilters(): void
    {
        session([
            self::SESSION_KEY => [
                'displayMode' => $this->displayMode,
                'typeFilter' => $this->typeFilter,
                'payerFilter' => $this->payerFilter,
            ],
        ]);
    }
}
