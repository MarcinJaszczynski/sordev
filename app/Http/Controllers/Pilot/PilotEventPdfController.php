<?php

namespace App\Http\Controllers\Pilot;

use App\Http\Controllers\Admin\EventPrintPdfController;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PilotEventPdfController extends Controller
{
    private const ALLOWED_AUDIENCES = ['pilot', 'folder'];

    public function download(Request $request, Event $event, string $audience)
    {
        abort_unless(in_array($audience, self::ALLOWED_AUDIENCES, true), 404);

        Gate::authorize('viewPilotDetails', $event);

        return app(EventPrintPdfController::class)->download($request, $event, $audience);
    }
}
