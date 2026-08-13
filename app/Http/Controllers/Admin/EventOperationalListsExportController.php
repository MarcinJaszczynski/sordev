<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Listy operacyjne (autokar / ubezpieczenie CSV) z rosteru EventParticipant.
 */
class EventOperationalListsExportController extends Controller
{
    public function __invoke(Event $event, string $type = 'bus'): StreamedResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $event);

        abort_unless(Schema::hasTable('event_participants'), 404);

        $type = in_array($type, ['bus', 'insurance', 'roster'], true) ? $type : 'bus';
        $filename = Str::slug($event->name).'-lista-'.$type.'.csv';

        $participants = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', EventParticipant::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->streamDownload(function () use ($participants, $type): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            $headers = match ($type) {
                'insurance' => ['Lp', 'Nazwisko', 'Imię', 'Data urodzenia', 'PESEL', 'Dieta', 'Zgody'],
                'bus' => ['Lp', 'Nazwisko', 'Imię', 'Telefon', 'Uwagi / dieta'],
                default => ['Lp', 'Nazwisko', 'Imię', 'E-mail', 'Telefon', 'Dieta', 'Zgody', 'Nr rezerwacji'],
            };
            fputcsv($out, $headers, ';');

            $lp = 1;
            foreach ($participants as $p) {
                $row = match ($type) {
                    'insurance' => [
                        $lp,
                        $p->last_name,
                        $p->first_name,
                        $p->birth_date?->format('Y-m-d'),
                        $p->pesel,
                        $p->diet,
                        $p->consentsCompletedLabel(),
                    ],
                    'bus' => [
                        $lp,
                        $p->last_name,
                        $p->first_name,
                        $p->phone,
                        $p->diet,
                    ],
                    default => [
                        $lp,
                        $p->last_name,
                        $p->first_name,
                        $p->email,
                        $p->phone,
                        $p->diet,
                        $p->consentsCompletedLabel(),
                        $p->booking_reference,
                    ],
                };
                fputcsv($out, $row, ';');
                $lp++;
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
