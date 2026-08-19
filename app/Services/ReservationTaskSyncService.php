<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\SystemTaskFactory;
use App\Support\Tasks\TaskQueryFilters;
use Carbon\Carbon;

class ReservationTaskSyncService
{
    public function sync(Reservation $reservation): void
    {
        $reservation->loadMissing(['event', 'programPoint.templatePoint', 'contractor']);

        $this->syncConfirmTask($reservation);
        $this->syncDepositTask($reservation);
    }

    public function retireAll(Reservation $reservation): void
    {
        $this->retireTasks($this->fingerprint($reservation->id, 'confirm'));
        $this->retireTasks($this->fingerprint($reservation->id, 'deposit'));
    }

    protected function syncConfirmTask(Reservation $reservation): void
    {
        $fingerprint = $this->fingerprint($reservation->id, 'confirm');
        $event = $reservation->event;

        if (
            ! $event
            || ! $reservation->exists
            || $reservation->trashed()
            || ! $reservation->confirm_by
            || in_array($reservation->status, ['confirmed', 'completed', 'cancelled', 'not_required'], true)
            || filled($reservation->confirmed_at)
        ) {
            $this->retireTasks($fingerprint);

            return;
        }

        $context = $this->contextLabel($reservation);

        $this->upsertTask(
            fingerprint: $fingerprint,
            title: 'Potwierdź rezerwację: '.$context,
            description: 'Termin potwierdzenia rezerwacji u dostawcy. Nr: '.($reservation->booking_reference ?: '#'.$reservation->id).'.',
            dueDate: Carbon::parse($reservation->confirm_by),
            event: $event,
            reservation: $reservation,
        );
    }

    protected function syncDepositTask(Reservation $reservation): void
    {
        $fingerprint = $this->fingerprint($reservation->id, 'deposit');
        $event = $reservation->event;

        if (
            ! $event
            || ! $reservation->exists
            || $reservation->trashed()
            || ! $reservation->deposit_due_at
            || filled($reservation->deposit_paid_at)
            || in_array($reservation->status, ['cancelled', 'not_required'], true)
        ) {
            $this->retireTasks($fingerprint);

            return;
        }

        $context = $this->contextLabel($reservation);
        $amount = $reservation->reserved_amount !== null
            ? number_format((float) $reservation->reserved_amount, 2, ',', ' ').' PLN'
            : '—';

        $this->upsertTask(
            fingerprint: $fingerprint,
            title: 'Zaliczka rezerwacji: '.$context,
            description: 'Termin zaliczki u dostawcy. Kwota: '.$amount.'. Nr: '.($reservation->booking_reference ?: '#'.$reservation->id).'.',
            dueDate: Carbon::parse($reservation->deposit_due_at),
            event: $event,
            reservation: $reservation,
        );
    }

    protected function contextLabel(Reservation $reservation): string
    {
        $parts = [];

        if ($reservation->event?->name) {
            $parts[] = $reservation->event->name;
        }

        $pointName = $reservation->programPoint?->name
            ?: $reservation->programPoint?->templatePoint?->name;

        if ($pointName) {
            $parts[] = $pointName;
        } elseif ($reservation->contractor?->name) {
            $parts[] = $reservation->contractor->name;
        }

        return $parts !== [] ? implode(' • ', $parts) : ('#'.$reservation->id);
    }

    protected function upsertTask(
        string $fingerprint,
        string $title,
        string $description,
        Carbon $dueDate,
        Event $event,
        Reservation $reservation,
    ): void {
        SystemTaskFactory::upsertShared(
            taskable: $reservation,
            fingerprint: $fingerprint,
            title: $title,
            description: $description,
            priority: TaskPriority::Urgent,
            dueDate: $dueDate,
            // Opiekun imprezy obsługuje klientów/pilotów — zadania rezerwacji/zaliczek idą na biuro.
            eventForAssignee: null,
            url: AdminPanelUrls::reservationEdit($reservation),
        );
    }

    protected function retireTasks(string $fingerprint): void
    {
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        if (! $completedStatusId) {
            return;
        }

        $query = Task::query()->where('description', 'like', '%'.$fingerprint.'%');
        TaskQueryFilters::excludeFinished($query);
        $query->update(['status_id' => $completedStatusId]);
    }

    protected function fingerprint(int $reservationId, string $kind): string
    {
        return '[reservation-task:'.$reservationId.':'.$kind.']';
    }
}
