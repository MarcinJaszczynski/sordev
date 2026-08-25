<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventInsurancePolicy;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Lustro polisy operacyjnej → EventSettlementDocument.
 *
 * SSoT: event_insurance_policies (z lustrem na events.insurance_*).
 * Finanse widzą plik jako dokument rozliczenia podpięty do kosztów insurance_day tej polisy.
 */
final class EventInsurancePolicySettlementSync
{
    public const DOCUMENT_TYPE = 'insurance_policy';

    public const SOURCE_MARKER = 'source:event_insurance_policy';

    public const POLICY_ID_MARKER_PREFIX = 'policy_id:';

    /**
     * Sync wszystkich polis imprezy (lub legacy lustro events.* gdy brak tabeli polis).
     *
     * @return list<EventSettlementDocument>
     */
    public function sync(Event $event, bool $ensureCosts = true): array
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return [];
        }

        if (Schema::hasTable('event_insurance_policies')) {
            $documents = [];
            foreach ($event->insurancePolicies()->orderBy('id')->get() as $policy) {
                $doc = $this->syncPolicy($policy, $ensureCosts);
                if ($doc) {
                    $documents[] = $doc;
                }
            }

            return $documents;
        }

        $legacy = $this->syncLegacyEventMirror($event, $ensureCosts);

        return $legacy ? [$legacy] : [];
    }

    public function syncPolicy(EventInsurancePolicy $policy, bool $ensureCosts = true): ?EventSettlementDocument
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            return null;
        }

        $event = $policy->event ?? $policy->event()->first();
        if (! $event) {
            return null;
        }

        $path = Event::normalizeInsuranceDocumentPath($policy->document_path);

        if (! filled($path)) {
            $this->removeSyncedDocumentForPolicy($event, $policy->id);

            return null;
        }

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if ($ensureCosts) {
            try {
                $settlement->importFromEvent();
            } catch (\Throwable $e) {
                Log::warning('EventInsurancePolicySettlementSync: importFromEvent failed', [
                    'event_id' => $event->id,
                    'policy_id' => $policy->id,
                    'settlement_id' => $settlement->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $settlement = $settlement->fresh() ?? $settlement;
        }

        $existing = $this->findSyncedDocumentForPolicy($settlement, $policy->id);
        $mirroredPath = $this->mirrorFile((string) $path, $event, $policy->id, $existing);

        if ($mirroredPath === null) {
            Log::warning('EventInsurancePolicySettlementSync: brak pliku źródłowego polisy', [
                'event_id' => $event->id,
                'policy_id' => $policy->id,
                'path' => $path,
            ]);

            return $existing;
        }

        $dayIds = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('event_insurance_policy_id', $policy->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $costQuery = $settlement->costs()->where('source_type', 'insurance_day');
        if ($dayIds !== []) {
            $costQuery->whereIn('source_id', $dayIds);
        } else {
            // Polisa bez linkage (np. zapisana przed produktami) — podłącz wszystkie koszty insurance_day.
            // Gdy pojawią się powiązania policy_id, kolejne sync zawężą listę.
        }

        $costIds = $costQuery
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $payload = [
            'document_type' => self::DOCUMENT_TYPE,
            'document_number' => filled($policy->policy_number) ? (string) $policy->policy_number : null,
            'total_amount' => filled($policy->amount) ? (float) $policy->amount : null,
            'payment_date' => $policy->paid_at,
            'payer_scope' => 'office',
            'linked_cost_ids' => $costIds,
            'files' => [$mirroredPath],
            'notes' => $this->buildNotesForPolicy($policy),
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

    /**
     * @deprecated Używane gdy brak tabeli polis — lustro events.insurance_*
     */
    private function syncLegacyEventMirror(Event $event, bool $ensureCosts = true): ?EventSettlementDocument
    {
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
        $mirroredPath = $this->mirrorFile((string) $path, $event, null, $existing);

        if ($mirroredPath === null) {
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

        $settlement = $this->resolveSettlement($event);
        if (! $settlement) {
            return;
        }

        if (Schema::hasTable('event_insurance_policies')) {
            foreach ($event->insurancePolicies()->pluck('id') as $policyId) {
                $this->removeSyncedDocumentForPolicy($event, (int) $policyId);
            }

            // Legacy markers without policy_id
            $legacy = $this->findSyncedDocument($settlement);
            if ($legacy && ! str_contains((string) $legacy->notes, self::POLICY_ID_MARKER_PREFIX)) {
                $legacy->delete();
            }

            return;
        }

        $document = $this->findSyncedDocument($settlement);
        if ($document) {
            $document->delete();
        }
    }

    public function removeSyncedDocumentForPolicy(Event $event, int $policyId): void
    {
        $settlement = $this->resolveSettlement($event);
        if (! $settlement) {
            return;
        }

        $document = $this->findSyncedDocumentForPolicy($settlement, $policyId);
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

    public function findSyncedDocumentForPolicy(EventSettlement $settlement, int $policyId): ?EventSettlementDocument
    {
        $marker = self::POLICY_ID_MARKER_PREFIX.$policyId;

        return $settlement->documents()
            ->where('document_type', self::DOCUMENT_TYPE)
            ->where('notes', 'like', '%'.self::SOURCE_MARKER.'%')
            ->where('notes', 'like', '%'.$marker.'%')
            ->latest('id')
            ->first();
    }

    private function resolveSettlement(Event $event): ?EventSettlement
    {
        if ($event->relationLoaded('activeSettlement') && $event->activeSettlement) {
            return $event->activeSettlement;
        }

        return $event->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
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

    private function buildNotesForPolicy(EventInsurancePolicy $policy): string
    {
        $lines = [
            self::SOURCE_MARKER,
            self::POLICY_ID_MARKER_PREFIX.$policy->id,
            'Polisa imprezy (synchronizacja z Operacje → Ubezpieczenia).',
        ];

        if (filled($policy->policy_number)) {
            $lines[] = 'Nr polisy: '.$policy->policy_number;
        }

        return implode("\n", $lines);
    }

    private function mirrorFile(
        string $sourcePath,
        Event $event,
        ?int $policyId,
        ?EventSettlementDocument $existing,
    ): ?string {
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
        $policyPart = $policyId ? '-p'.$policyId : '';
        $target = sprintf(
            'event-settlement-documents/polisa-imprezy-%d%s-%s.%s',
            (int) $event->id,
            $policyPart,
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
