<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventInvoicePdfMergeService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventInvoicePdfController extends Controller
{
    public function download(Event $event, EventInvoicePdfMergeService $service): BinaryFileResponse
    {
        try {
            $result = $service->mergeToTempFile($event);
        } catch (\RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
    }
}
