<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventParticipantInsuranceExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventParticipantInsuranceExportController extends Controller
{
    public function __invoke(Event $event, string $format = 'xlsx'): StreamedResponse|BinaryFileResponse
    {
        $format = strtolower($format);
        $basename = Str::slug($event->name).'-lista-uczestnikow-ubezpieczenie';

        if ($format === 'csv') {
            return Excel::download(new EventParticipantInsuranceExport($event), $basename.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new EventParticipantInsuranceExport($event), $basename.'.xlsx');
    }
}
