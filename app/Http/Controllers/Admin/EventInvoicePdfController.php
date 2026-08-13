<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\EventResource;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventInvoicePdfMergeService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventInvoicePdfController extends Controller
{
    public function download(Event $event, EventInvoicePdfMergeService $service): BinaryFileResponse|RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $event);

        try {
            $result = $service->mergeToTempFile($event);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->to(EventResource::getUrl('documents', ['record' => $event]))
                ->with('error', $exception->getMessage());
        }

        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
    }
}
