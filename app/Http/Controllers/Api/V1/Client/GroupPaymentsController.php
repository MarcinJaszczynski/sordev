<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\ClientGroupPaymentsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupPaymentsController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function show(
        Request $request,
        Event $event,
        ClientAccessService $access,
        ClientGroupPaymentsService $groupPayments,
    ): JsonResponse {
        $this->authorizeClientDetails($event);
        abort_unless($access->isGuardian($request->user(), $event), 403);

        return $this->success([
            'event_id' => $event->id,
            'summary' => $groupPayments->summaryFor($request->user(), $event),
            'rows' => $groupPayments->rowsFor($request->user(), $event)->values(),
        ]);
    }
}
