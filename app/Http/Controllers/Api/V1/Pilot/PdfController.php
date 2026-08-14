<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Http\Controllers\Pilot\PilotEventPdfController;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PdfController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function download(Request $request, Event $event, string $audience)
    {
        $this->authorizePilotDetails($event);

        abort_unless(in_array($audience, ['pilot', 'folder'], true), 404);

        return app(PilotEventPdfController::class)->download($request, $event, $audience);
    }
}
