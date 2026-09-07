<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskPriority;
use App\Events\EventStatusChanged;
use App\Mail\EventStatusChangedMail;
use App\Models\Event;
use App\Services\Sms\SmsChannelInterface;
use App\Support\AdminPanelUrls;
use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Automatyzacje po zmianie statusu imprezy (zadania biurowe + SMS/mail do klienta).
 */
final class EventStatusAutomationService
{
    public function __construct(
        private readonly SmsChannelInterface $sms,
    ) {}

    public function handle(EventStatusChanged $change): void
    {
        $event = $change->event;
        $from = $change->previousStatus;
        $to = $change->newStatus;

        Log::info('EventStatusChanged', [
            'event_id' => $event->id,
            'from' => $from,
            'to' => $to,
        ]);

        match ($to) {
            Event::STATUS_CONFIRMED => $this->createOfficeTask(
                $event,
                'Impreza potwierdzona — lista kontrolna: '.$this->label($event),
                'Umowy, zaliczki, udostępnienie pilotowi i portalowi klienta.',
                TaskPriority::Urgent,
            ),
            // Oferta / rezerwacja wstępna / do rozliczenia — bez auto-taska (zaśmiecało skrzynkę).
            default => null,
        };

        $this->notifyClient($event, $to);
    }

    private function notifyClient(Event $event, string $to): void
    {
        if (! in_array($to, [Event::STATUS_OFFER, Event::STATUS_CONFIRMED], true)) {
            return;
        }

        $statusLabel = $this->statusLabel($to);
        $message = match ($to) {
            Event::STATUS_OFFER => sprintf(
                'Przygotowaliśmy ofertę wycieczki „%s” (%s). Skontaktujemy się w sprawie szczegółów.',
                $event->name ?: 'Wasza wycieczka',
                $event->code ?: '#'.$event->id,
            ),
            Event::STATUS_CONFIRMED => sprintf(
                'Potwierdzamy wycieczkę „%s” (%s). Wkrótce prześlemy informacje o umowie i płatnościach.',
                $event->name ?: 'Wasza wycieczka',
                $event->code ?: '#'.$event->id,
            ),
            default => sprintf('Status wycieczki: %s.', $statusLabel),
        };

        $phone = trim((string) ($event->client_phone ?? ''));
        if ($phone !== '') {
            $this->sms->send(
                $phone,
                sprintf('[%s] %s', $event->code ?: '#'.$event->id, $message),
            );
        }

        $email = trim((string) ($event->client_email ?? ''));
        if ($email !== '') {
            Mail::to($email)->send(new EventStatusChangedMail(
                event: $event,
                newStatus: $to,
                statusLabel: $statusLabel,
                messageBody: $message,
            ));
        }
    }

    private function label(Event $event): string
    {
        return ($event->code ?: '#'.$event->id).' — '.($event->name ?: 'Impreza');
    }

    private function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        return Event::getStatusOptions()[$status] ?? $status;
    }

    private function createOfficeTask(Event $event, string $title, string $description, TaskPriority $priority): void
    {
        SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':'.$event->status,
            title: $title,
            description: $description,
            priority: $priority,
            eventForAssignee: $event,
            url: AdminPanelUrls::eventEdit($event),
            onlyOpenWhenFinding: false,
        );
    }
}
