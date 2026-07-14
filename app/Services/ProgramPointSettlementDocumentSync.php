<?php

namespace App\Services;

use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Auth;

class ProgramPointSettlementDocumentSync
{
    /**
     * @param  array{
     *     document_id?: int|null,
     *     document_type?: string|null,
     *     document_number?: string|null,
     *     document_files?: array<int, string>|null
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

        $normalizedFiles = collect(is_array($documentFiles) ? $documentFiles : [])
            ->map(fn ($path) => StoragePath::normalize(is_string($path) ? $path : null))
            ->filter()
            ->values()
            ->all();

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
                    ->push($cost->id)
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

        return $document->fresh();
    }

    public function loadDocumentDataForCost(EventSettlementCost $cost): array
    {
        $document = EventSettlementDocument::query()
            ->where('settlement_id', $cost->settlement_id)
            ->whereJsonContains('linked_cost_ids', $cost->id)
            ->latest('id')
            ->first();

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

    private function unlinkDocument(int $documentId, EventSettlementCost $cost): void
    {
        $document = EventSettlementDocument::query()->find($documentId);

        if (! $document) {
            return;
        }

        $linkedIds = collect($document->linked_cost_ids ?? [])
            ->reject(fn ($id) => (int) $id === (int) $cost->id)
            ->values()
            ->all();

        if ($linkedIds === []) {
            $document->delete();

            return;
        }

        $document->update(['linked_cost_ids' => $linkedIds]);
    }
}
