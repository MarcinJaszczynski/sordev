<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AgreementDocumentService;
use App\Services\ClientAccessService;
use Illuminate\Support\Facades\Gate;

class ClientContractPdfController extends Controller
{
    public function __invoke(Event $event, AgreementDocumentService $documents, ClientAccessService $access)
    {
        Gate::authorize('viewClientPortalDetails', $event);

        $contract = $access->accessibleContract(auth()->user(), $event, \App\Models\EventPortalAccess::ROLE_PARTICIPANT)
            ?? $access->accessibleContract(auth()->user(), $event, \App\Models\EventPortalAccess::ROLE_GUARDIAN);

        abort_unless($contract, 404);

        $bytes = $documents->renderPdfBytes($contract);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="umowa-'.$contract->id.'.pdf"',
        ]);
    }
}
