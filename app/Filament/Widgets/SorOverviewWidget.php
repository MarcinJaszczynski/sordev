<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\VendorInvoice;
use App\Support\Tasks\TaskQueryFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class SorOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $eventsInProgress = Event::query()
            ->whereIn('status', [
                Event::STATUS_CONFIRMED,
                Event::STATUS_PROVISIONAL_RESERVATION,
                Event::STATUS_TO_SETTLE,
                Event::STATUS_OFFER,
            ])
            ->count();

        $openSettlements = EventSettlement::query()
            ->whereIn('status', ['draft', 'active'])
            ->count();

        $pendingReservations = Reservation::query()
            ->where('status', 'pending')
            ->count();

        $openTasksQuery = Task::query();
        TaskQueryFilters::officeOnly($openTasksQuery);
        TaskQueryFilters::excludeFinished($openTasksQuery);
        $openTasks = $openTasksQuery->count();

        $unpaidPilotFunds = Schema::hasColumn('events', 'pilot_funds_paid')
            ? Event::query()
                ->whereNotNull('assigned_to')
                ->where('pilot_funds_paid', false)
                ->whereIn('status', [
                    Event::STATUS_CONFIRMED,
                    Event::STATUS_TO_SETTLE,
                    Event::STATUS_SETTLED,
                ])
                ->count()
            : null;

        $lastBackup = $this->lastBackupLabel();

        $stats = [
            Stat::make('Imprezy w toku', (string) $eventsInProgress)
                ->description('Potwierdzone / w realizacji')
                ->url(route('filament.admin.resources.events.index'))
                ->color('primary'),
            Stat::make('Otwarte rozliczenia', (string) $openSettlements)
                ->description('Szkice i aktywne')
                ->url(route('filament.admin.resources.event-settlements.index'))
                ->color('warning'),
        ];

        if ($unpaidPilotFunds !== null) {
            $stats[] = Stat::make('Wypłata pilota', (string) $unpaidPilotFunds)
                ->description('Do wypłaty (z pilotem)')
                ->url(route('filament.admin.resources.events.index', [
                    'tableFilters' => ['pilot_funds_paid' => ['value' => '0']],
                ]))
                ->color($unpaidPilotFunds > 0 ? 'danger' : 'success');
        }

        if (Schema::hasTable('vendor_invoices')) {
            $unmatched = VendorInvoice::query()->where('matching_status', 'unmatched')->count();
            $stats[] = Stat::make('Faktury do opracowania', (string) $unmatched)
                ->description('Stos KSeF')
                ->url(route('filament.admin.pages.vendor-invoice-inbox-page'))
                ->color($unmatched > 0 ? 'warning' : 'gray');
        }

        $stats[] = Stat::make('Rezerwacje', (string) $pendingReservations)
            ->description('Oczekujące')
            ->url(route('filament.admin.resources.reservations.index'))
            ->color('info');
        $stats[] = Stat::make('Zadania otwarte', (string) $openTasks)
            ->description('Niezamknięte statusy')
            ->url(route('filament.admin.resources.tasks.index'))
            ->color('gray');
        $stats[] = Stat::make('Ostatnia kopia', $lastBackup)
            ->description('Katalog storage/backups')
            ->url(route('filament.admin.pages.backup-manager'))
            ->color('success');

        return $stats;
    }

    private function lastBackupLabel(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            return 'Brak';
        }

        $files = glob($dir.'/backup_*.zip') ?: [];
        if ($files === []) {
            return 'Brak';
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return date('d.m.Y H:i', (int) filemtime($files[0]));
    }
}
