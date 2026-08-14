<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pilot;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAudienceTrip;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends BaseApiController
{
    use AuthorizesAudienceTrip;

    public function index(Event $event): JsonResponse
    {
        $this->authorizePilotDetails($event);

        $eventDocuments = [];
        if (Schema::hasTable('event_documents')) {
            $eventDocuments = EventDocument::query()
                ->where('event_id', $event->id)
                ->where('attach_to_pilot_pdf', true)
                ->when(
                    Schema::hasColumn('event_documents', 'approval_status'),
                    fn ($q) => $q->where(function ($inner) {
                        $inner->whereNull('approval_status')
                            ->orWhere('approval_status', '!=', 'rejected');
                    }),
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (EventDocument $doc) => [
                    'id' => $doc->id,
                    'name' => $doc->name,
                    'type' => 'event_document',
                    'url' => $doc->public_url,
                ])
                ->values()
                ->all();
        }

        $settlementDocuments = [];
        if (Schema::hasTable('event_settlement_documents')
            && Schema::hasColumn('event_settlement_documents', 'attach_to_pilot_pdf')
            && Schema::hasTable('event_settlements')) {
            $settlementIds = EventSettlement::query()->where('event_id', $event->id)->pluck('id');
            if ($settlementIds->isNotEmpty()) {
                $settlementDocuments = EventSettlementDocument::query()
                    ->whereIn('settlement_id', $settlementIds)
                    ->where('attach_to_pilot_pdf', true)
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (EventSettlementDocument $doc) => [
                        'id' => $doc->id,
                        'name' => $doc->document_number ?: $doc->vendor_name ?: ('Dokument #'.$doc->id),
                        'type' => 'settlement_document',
                        'document_type' => $doc->document_type,
                    ])
                    ->values()
                    ->all();
            }
        }

        $insurance = collect($event->insuranceFilesForPilot())
            ->map(function (array $file): ?array {
                $path = $file['path'] ?? null;
                if (! filled($path) || ! Storage::disk('public')->exists((string) $path)) {
                    return null;
                }

                return [
                    'key' => (string) $file['key'],
                    'label' => (string) $file['label'],
                    'url' => Storage::disk('public')->url((string) $path),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $this->success([
            'event_id' => $event->id,
            'event_documents' => $eventDocuments,
            'settlement_documents' => $settlementDocuments,
            'insurance_documents' => $insurance,
            'pdf' => [
                'pilot' => url('/api/v1/pilot/trips/'.$event->id.'/pdf/pilot'),
                'folder' => url('/api/v1/pilot/trips/'.$event->id.'/pdf/folder'),
            ],
        ]);
    }
}
