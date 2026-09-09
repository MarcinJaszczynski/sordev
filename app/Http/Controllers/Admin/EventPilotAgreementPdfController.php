<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\PilotAgreementDocumentService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventPilotAgreementPdfController extends Controller
{
    public function __invoke(Event $event, PilotAgreementDocumentService $service): Response|StreamedResponse
    {
        $this->authorize('update', $event);

        $agreement = $service->currentForEvent($event);
        if (! $agreement?->hasPdf()) {
            abort(404, 'Brak wygenerowanej umowy pilota.');
        }

        $bytes = $service->readPdfBytes($agreement);
        if ($bytes === null) {
            abort(404, 'Plik PDF umowy nie istnieje.');
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$service->downloadFilename($agreement).'"',
        ]);
    }
}
