<?php

namespace App\Services;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProgramPointSettlementDocumentSync
{
    /**
     * @param  array{
     *     document_id?: int|null,
     *     document_type?: string|null,
     *     document_number?: string|null,
     *     document_files?: array<int, mixed>|null
     * }  $data
     */
    public function syncForCost(EventSettlement $settlement, EventSettlementCost $cost, array $data, string $prefix = ''): ?EventSettlementDocument
    {
        $documentId = $data[$prefix.'document_id'] ?? null;
        $documentType = $data[$prefix.'document_type'] ?? null;
        $documentNumber = $data[$prefix.'document_number'] ?? null;
        $documentFiles = $data[$prefix.'document_files'] ?? null;

        $hasContent = filled($documentType)
            || filled($documentNumber)
            || (is_array($documentFiles) && $documentFiles !== []);

        if (! $hasContent) {
            if ($documentId) {
                $this->unlinkDocument((int) $documentId, $cost);
            }

            return null;
        }

        $normalizedFiles = $this->normalizeDocumentFiles(is_array($documentFiles) ? $documentFiles : []);

        $payload = [
            'document_type' => $documentType ?: 'invoice',
            'document_number' => $documentNumber,
            'total_amount' => $cost->actual_amount ?? $cost->advance_amount ?? $cost->planned_amount,
            'currency_id' => $cost->actual_currency_id ?? $cost->planned_currency_id,
            'payment_date' => $cost->paid_at ?? $cost->advance_due_date,
            'payment_method' => $cost->payment_method,
            'payer_scope' => $cost->paid_by === 'pilot' ? 'pilot' : 'office',
            'linked_cost_ids' => [(int) $cost->id],
            'notes' => $cost->notes,
        ];

        if ($normalizedFiles !== []) {
            $payload['files'] = $normalizedFiles;
        }

        $document = null;

        if ($documentId) {
            $document = EventSettlementDocument::query()
                ->where('settlement_id', $settlement->id)
                ->find($documentId);

            if ($document) {
                $linkedIds = collect($document->linked_cost_ids ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->push((int) $cost->id)
                    ->unique()
                    ->values()
                    ->all();

                $document->update(array_merge($payload, [
                    'linked_cost_ids' => $linkedIds,
                ]));
            }
        }

        if (! $document) {
            $document = $settlement->documents()->create(array_merge($payload, [
                'approval_status' => 'pending',
                'created_by' => Auth::id(),
            ]));
        }

        if ($settlement->event_id) {
            EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $settlement->event_id);
        }

        return $document->fresh();
    }

    public function loadDocumentDataForCost(EventSettlementCost $cost): array
    {
        // Filtr w PHP: MySQL JSON_CONTAINS bywa wrażliwy na int vs string w linked_cost_ids.
        $document = EventSettlementDocument::query()
            ->where('settlement_id', $cost->settlement_id)
            ->latest('id')
            ->get()
            ->first(function (EventSettlementDocument $doc) use ($cost): bool {
                return collect($doc->linked_cost_ids ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->contains((int) $cost->id);
            });

        if (! $document) {
            return [
                'document_id' => null,
                'document_type' => null,
                'document_number' => $cost->document_number,
                'document_files' => [],
            ];
        }

        return [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number ?: $cost->document_number,
            'document_files' => $document->files ?? [],
        ];
    }

    /**
     * @param  array<int, mixed>  $documentFiles
     * @return list<string>
     */
    private function normalizeDocumentFiles(array $documentFiles): array
    {
        return collect($documentFiles)
            ->map(function ($path) {
                if ($path instanceof TemporaryUploadedFile || $path instanceof UploadedFile) {
                    return StoragePath::normalize($path->store('event-settlement-documents', 'public'));
                }

                return StoragePath::normalize(is_string($path) ? $path : null);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function unlinkDocument(int $documentId, EventSettlementCost $cost): void
    {
        $document = EventSettlementDocument::query()->find($documentId);

        if (! $document) {
            return;
        }

        $linkedIds = collect($document->linked_cost_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $cost->id)
            ->values()
            ->all();

        if ($linkedIds === []) {
            $document->delete();

            return;
        }

        $document->update(['linked_cost_ids' => $linkedIds]);
    }
}
