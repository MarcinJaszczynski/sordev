<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Services\FileSecurityService;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class AttachSettlementCostDocumentAction
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(
        EventSettlementCost $planCost,
        array $files,
        string $documentType = 'invoice',
        ?string $documentNumber = null,
        ?string $notes = null,
    ): EventSettlementDocument {
        if ($files === []) {
            throw new InvalidArgumentException('Dodaj co najmniej jeden plik.');
        }

        $type = array_key_exists($documentType, EventSettlementDocument::$documentTypes)
            ? $documentType
            : 'invoice';

        return DB::transaction(function () use ($planCost, $files, $type, $documentNumber, $notes): EventSettlementDocument {
            $settlement = $planCost->settlement()->firstOrFail();
            \Illuminate\Support\Facades\Gate::authorize('attachCostDocument', $settlement);
            $stored = [];

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $validation = FileSecurityService::validateDocumentUpload($file);
                if (! $validation['safe']) {
                    throw new InvalidArgumentException(
                        'Niebezpieczny plik: '.implode('; ', $validation['errors'] ?? ['odrzucono'])
                    );
                }

                $path = StoragePath::normalize($file->store('event-settlement-documents', 'public'));
                if ($path) {
                    $stored[] = $path;
                }
            }

            if ($stored === []) {
                throw new InvalidArgumentException('Nie udało się zapisać plików.');
            }

            return $settlement->documents()->create([
                'document_type' => $type,
                'document_number' => $documentNumber,
                'total_amount' => $planCost->planned_amount_pln ?? $planCost->planned_amount,
                'currency_id' => $planCost->planned_currency_id,
                'payment_method' => $planCost->payment_method,
                'payer_scope' => ($planCost->paid_by ?? 'office') === 'pilot' ? 'pilot' : 'office',
                'linked_cost_ids' => [(int) $planCost->id],
                'files' => $stored,
                'notes' => $notes,
                'approval_status' => 'pending',
                'created_by' => Auth::id(),
            ])->fresh();
        });
    }

    public function delete(EventSettlementDocument $document, EventSettlementCost $planCost): void
    {
        $linked = collect($document->linked_cost_ids ?? [])->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) $planCost->id, $linked, true)) {
            throw new InvalidArgumentException('Dokument nie jest powiązany z tą pozycją.');
        }

        $remaining = array_values(array_filter(
            $linked,
            fn (int $id): bool => $id !== (int) $planCost->id,
        ));

        if ($remaining === []) {
            foreach ($document->files ?? [] as $path) {
                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }
            $document->delete();

            return;
        }

        $document->update(['linked_cost_ids' => $remaining]);
    }
}
