<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Lustro polisy imprezy (events.insurance_document_path) → EventSettlementDocument.
 *
 * Operacje trzymają SSoT gotowości; Finanse (Koszty / Dok. rozliczenia) widzą ten sam plik
 * jako dokument rozliczenia podpięty do pozycji insurance_day.
 *
 * Plik jest kopiowany do event-settlement-documents/, żeby usunięcie dokumentu w Finansach
 * nie kasowało oryginału z Operacji (i odwrotnie).
 */
final class EventInsurancePolicySettlementSync
{
    public const DOCUMENT_TYPE = 'insurance_policy';

    public const SOURCE_MARKER = 'source:event_insurance_policy';

    /**
     * @param  bool  $ensureCosts  true = import pozycji insurance_day przed podpięciem
     */
    public function sync(Event $event, bool $ensureCosts = true): ?EventSettlementDocument
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return null;
        }

        $path = Event::normalizeInsuranceDocumentPath($event->insurance_document_path);

        if (! filled($path)) {
            $this->removeSyncedDocument($event);

            return null;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if ($ensureCosts) {
            try {
                $settlement->importFromEvent();
            } catch (\Throwable $e) {
                Log::warning('EventInsurancePolicySettlementSync: importFromEvent failed', [
                    'event_id' => $event->id,
                    'settlement_id' => $settlement->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $settlement = $settlement->fresh() ?? $settlement;
        }

        $existing = $this->findSyncedDocument($settlement);
        $mirroredPath = $this->mirrorFile((string) $path, $event, $existing);

        if ($mirroredPath === null) {
            Log::warning('EventInsurancePolicySettlementSync: brak pliku źródłowego polisy', [
                'event_id' => $event->id,
                'path' => $path,
            ]);

            return $existing;
        }

        $costIds = $settlement->costs()
            ->where('source_type', 'insurance_day')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $payload = [
            'document_type' => self::DOCUMENT_TYPE,
            'document_number' => filled($event->insurance_policy_number)
                ? (string) $event->insurance_policy_number
                : null,
            'total_amount' => filled($event->insurance_amount) ? (float) $event->insurance_amount : null,
            'payment_date' => $event->insurance_paid_at,
            'payer_scope' => 'office',
            'linked_cost_ids' => $costIds,
            'files' => [$mirroredPath],
            'notes' => $this->buildNotes($event),
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return $settlement->documents()->create(array_merge($payload, [
            'approval_status' => 'pending',
            'created_by' => Auth::id(),
        ]))->fresh();
    }

    public function removeSyncedDocument(Event $event): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return;
        }

        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : ($event->settlements()
                ->whereIn('status', ['draft', 'active', 'pilot_settled'])
                ->latest('id')
                ->first());

        if (! $settlement) {
            return;
        }

        $document = $this->findSyncedDocument($settlement);
        if ($document) {
            $document->delete();
        }
    }

    public function findSyncedDocument(EventSettlement $settlement): ?EventSettlementDocument
    {
        return $settlement->documents()
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('notes', 'like', '%'.self::SOURCE_MARKER.'%')
            ->latest('id')
            ->first();
    }

    private function buildNotes(Event $event): string
    {
        $lines = [
            self::SOURCE_MARKER,
            'Polisa imprezy (synchronizacja z Operacje → Ubezpieczenia).',
        ];

        if (filled($event->insurance_policy_number)) {
            $lines[] = 'Nr polisy: '.$event->insurance_policy_number;
        }

        return implode("\n", $lines);
    }

    private function mirrorFile(string $sourcePath, Event $event, ?EventSettlementDocument $existing): ?string
    {
        $sourcePath = StoragePath::normalize($sourcePath);
        if (! $sourcePath) {
            return null;
        }

        $disk = Storage::disk('public');
        $existingFiles = collect($existing?->files ?? [])
            ->filter(fn ($p): bool => is_string($p) && $p !== '')
            ->values();
        $existingFile = $existingFiles->first();

        if (! $disk->exists($sourcePath)) {
            return is_string($existingFile) && $disk->exists($existingFile)
                ? $existingFile
                : null;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'pdf';
        $target = sprintf(
            'event-settlement-documents/polisa-imprezy-%d-%s.%s',
            (int) $event->id,
            substr(sha1($sourcePath), 0, 12),
            strtolower($extension)
        );

        if (! $disk->exists($target)) {
            $disk->put($target, $disk->get($sourcePath));
        } else {
            $sourceBody = $disk->get($sourcePath);
            if (md5($sourceBody) !== md5($disk->get($target))) {
                $disk->put($target, $sourceBody);
            }
        }

        if (
            is_string($existingFile)
            && $existingFile !== $target
            && str_starts_with($existingFile, 'event-settlement-documents/')
            && $disk->exists($existingFile)
        ) {
            $disk->delete($existingFile);
        }

        return $target;
    }
}
