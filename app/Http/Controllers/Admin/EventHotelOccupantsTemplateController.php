<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventHotelOccupantsWorkbookExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventHotelOccupantsTemplateBuilder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventHotelOccupantsTemplateController extends Controller
{
    public function __invoke(Event $event, string $format = 'xlsx'): StreamedResponse|BinaryFileResponse
    {
        $format = strtolower($format);

        if ($format === 'csv') {
            return app(EventHotelOccupantsTemplateBuilder::class)->downloadCsv($event);
        }

        return Excel::download(
            new EventHotelOccupantsWorkbookExport($event),
            Str::slug($event->name).'-lista-osob-noclegi.xlsx',
        );
    }
}
