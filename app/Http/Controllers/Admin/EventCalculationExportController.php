<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventCalculationExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventCalculationPresenter;
use App\Support\DomPdfFactory;
use Maatwebsite\Excel\Facades\Excel;

class EventCalculationExportController extends Controller
{
    public function pdf(Event $event)
    {
        \Illuminate\Support\Facades\Gate::authorize('manageFinance', $event);

        $presenter = EventCalculationPresenter::for($event);
        $state = $presenter->widgetState();

        $pdf = DomPdfFactory::loadView('pdf.event-calculation', [
            'event' => $event,
            'state' => $state,
            'plannedTotal' => $presenter->plannedTotalPln(),
            'settlementPlanned' => $presenter->settlementPlannedPln(),
        ]);

        $filename = sprintf('kalkulacja_%s_%s.pdf', $event->id, now()->format('Y-m-d'));

        return $pdf->download($filename);
    }

    public function excel(Event $event)
    {
        \Illuminate\Support\Facades\Gate::authorize('manageFinance', $event);

        $filename = sprintf('kalkulacja_%s_%s.xlsx', $event->id, now()->format('Y-m-d'));

        return Excel::download(
            new EventCalculationExport($event),
            $filename,
        );
    }
}
