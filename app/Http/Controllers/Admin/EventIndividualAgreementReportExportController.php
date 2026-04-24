<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventIndividualAgreementsReportExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventIndividualAgreementReportExportController extends Controller
{
    public function __invoke(Request $request, Event $event, string $format): BinaryFileResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $writerType = $format === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        $fileName = sprintf(
            'event-%d-individual-agreements-report.%s',
            $event->id,
            $format,
        );

        return Excel::download(
            new EventIndividualAgreementsReportExport($event),
            $fileName,
            $writerType,
        );
    }
}
