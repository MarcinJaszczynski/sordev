<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function counts(Request $request): JsonResponse
    {
        $payload = NotificationService::getTopbarDataForUser($request->user()->id);

        return $this->success($payload);
    }
}
