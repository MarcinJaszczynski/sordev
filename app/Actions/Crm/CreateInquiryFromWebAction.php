<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Data\CreateInquiryFromWebData;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CreateInquiryFromWebAction
{
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
                'office_notes' => $notes,
                'notes' => $notes,
            ]);

            $task = null;
            if (Schema::hasTable('tasks')) {
                $payload = [
                    'title' => 'Lead WWW: '.$event->name,
                    'description' => $notes,
                ];

                if (Schema::hasColumn('tasks', 'event_id')) {
                    $payload['event_id'] = $event->id;
                }

                if (Schema::hasColumn('tasks', 'status_id') && class_exists(Task::class) && method_exists(Task::class, 'getDefaultStatusId')) {
                    $payload['status_id'] = Task::getDefaultStatusId();
                }

                try {
                    $task = Task::query()->create($payload);
                } catch (\Throwable) {
                    $task = null;
                }
            }

            return [
                'contact' => $contact,
                'event' => $event,
                'task' => $task,
            ];
        });
    }
}
