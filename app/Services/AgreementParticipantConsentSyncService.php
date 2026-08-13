<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventParticipant;
use App\Support\EventParticipantConsents;
use Illuminate\Support\Facades\Schema;

/**
 * Sync zgód z AgreementFlow (meta) → EventParticipant.consents (kanon).
 */
final class AgreementParticipantConsentSyncService
{
    public function syncFromAgreement(Contract|EventAgreement $agreement, ?string $ip = null): ?EventParticipant
    {
        if (! Schema::hasTable('event_participants') || ! $agreement->event_id) {
            return null;
        }

        $flowConsents = data_get($agreement->meta, 'flow.consents');
        if (! is_array($flowConsents) || ! ($flowConsents['accepted'] ?? false)) {
            return null;
        }

        $participant = $this->resolveParticipant($agreement);
        if (! $participant) {
            return null;
        }

        $acceptedAt = isset($flowConsents['accepted_at'])
            ? (string) $flowConsents['accepted_at']
            : now()->toIso8601String();

        $flags = EventParticipantConsents::flagsFromAgreementFlow($flowConsents);
        $consents = EventParticipantConsents::applyFlags($flags, $participant->consents, $acceptedAt);

        $payload = [];
        if (Schema::hasColumn('event_participants', 'consents')) {
            $payload['consents'] = $consents;
        }

        if (Schema::hasColumn('event_participants', 'parent_consent_at')
            && EventParticipantConsents::hasRequired($consents)
        ) {
            $payload['parent_consent_at'] = $participant->parent_consent_at ?? now();
            if (Schema::hasColumn('event_participants', 'parent_consent_ip') && $ip) {
                $payload['parent_consent_ip'] = $ip;
            }
        }

        if ($agreement instanceof Contract && Schema::hasColumn('event_participants', 'contract_id')) {
            $payload['contract_id'] = $agreement->id;
        }

        if ($agreement instanceof EventAgreement && Schema::hasColumn('event_participants', 'event_agreement_id')) {
            $payload['event_agreement_id'] = $agreement->id;
        }

        if ($agreement->participant_payment_id
            && Schema::hasColumn('event_participants', 'participant_payment_id')
        ) {
            $payload['participant_payment_id'] = $agreement->participant_payment_id;
        }

        if ($payload !== []) {
            $participant->forceFill($payload)->save();
        }

        return $participant->fresh();
    }

    private function resolveParticipant(Contract|EventAgreement $agreement): ?EventParticipant
    {
        $base = EventParticipant::query()
            ->where('event_id', $agreement->event_id)
            ->where('status', EventParticipant::STATUS_ACTIVE);

        if ($agreement instanceof Contract && Schema::hasColumn('event_participants', 'contract_id')) {
            $found = (clone $base)->where('contract_id', $agreement->id)->first();
            if ($found) {
                return $found;
            }
        }

        if ($agreement instanceof EventAgreement && Schema::hasColumn('event_participants', 'event_agreement_id')) {
            $found = (clone $base)->where('event_agreement_id', $agreement->id)->first();
            if ($found) {
                return $found;
            }
        }

        if ($agreement->participant_payment_id
            && Schema::hasColumn('event_participants', 'participant_payment_id')
        ) {
            $found = (clone $base)->where('participant_payment_id', $agreement->participant_payment_id)->first();
            if ($found) {
                return $found;
            }
        }

        $fullName = trim((string) ($agreement->participant_name ?: ''));
        if ($fullName === '') {
            return null;
        }

        $participant = new EventParticipant([
            'event_id' => $agreement->event_id,
            'source' => EventParticipant::SOURCE_AGREEMENT,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);
        $participant->setFullName($fullName);

        if ($agreement->participant_birth_date ?? null) {
            $participant->birth_date = $agreement->participant_birth_date;
        }
        if ($agreement->participant_email ?? null) {
            $participant->email = $agreement->participant_email;
        }
        if ($agreement->participant_phone ?? null) {
            $participant->phone = $agreement->participant_phone;
        }

        $participant->save();

        return $participant;
    }
}
