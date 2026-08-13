<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Data\CreateInquiryFromWebData;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Task;
use App\Services\EventInquiryNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateInquiryFromWebAction
{
    public function __construct(
        private readonly EventInquiryNotificationService $inquiryNotifications,
    ) {}

    /**
     * @return array{contact: Contact, event: Event, task: ?Task}
     */
    public function __invoke(CreateInquiryFromWebData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $name = trim((string) ($data->name ?? ''));
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $firstName = $parts[0] ?? 'Klient';
            $lastName = $parts[1] ?? 'WWW';

            $contact = Contact::query()->where('email', $data->email)->first();
            if ($contact) {
                $contact->fill([
                    'phone' => $data->telephone ?: $contact->phone,
                    'first_name' => $contact->first_name ?: $firstName,
                    'last_name' => $contact->last_name ?: $lastName,
                ])->save();
            } else {
                $contact = Contact::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $data->email,
                    'phone' => $data->telephone,
                ]);
            }

            $tripName = trim((string) ($data->eventName ?: 'Zapytanie ze strony'));
            $notes = collect([
                $data->message ? 'Wiadomość: '.$data->message : null,
                $data->startPlaceName ? 'Miejsce wyjazdu: '.$data->startPlaceName : null,
                $data->eventUrl ? 'URL oferty: '.$data->eventUrl : null,
                'Kontakt: '.$data->email.' / '.$data->telephone,
            ])->filter()->implode("\n");

            $event = Event::query()->create([
                'name' => Str::limit($tripName, 200),
                'client_name' => $name !== '' ? $name : trim($firstName.' '.$lastName),
                'client_email' => $data->email,
                'client_phone' => $data->telephone,
                'status' => Event::STATUS_INQUIRY,
                'participant_count' => 1,
                // start_date jest NOT NULL — lead WWW zwykle bez daty → placeholder do korekty w biurze
                'start_date' => now()->addMonth()->toDateString(),
                'duration_days' => 1,
                'office_notes' => $notes,
                'notes' => $notes,
            ]);

            // Ten sam write-path powiadomień co CreateEvent (notify office).
            $this->inquiryNotifications->notifyOfficeAboutNewInquiry($event);

            $task = Task::query()
                ->where('taskable_type', Event::class)
                ->where('taskable_id', $event->id)
                ->latest('id')
                ->first();

            return [
                'contact' => $contact,
                'event' => $event,
                'task' => $task,
            ];
        });
    }
}
