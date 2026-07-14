<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Models\LegacyEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillLegacyEventPurchasers extends Command
{
    protected $signature = 'legacy-events:backfill-purchasers {--dry-run : Tylko podgląd zmian bez zapisu}';

    protected $description = 'Uzupełnia brakujące dane zamawiającego w legacy_events i domyka mapowanie contractor_id.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $events = LegacyEvent::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('client_name')
                    ->orWhereNull('client_email')
                    ->orWhereNull('client_phone')
                    ->orWhereNull('client_contact_person')
                    ->orWhereNull('contractor_id');
            })
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            $this->info('Brak rekordów wymagających backfill.');

            return Command::SUCCESS;
        }

        $this->info('Rekordów do analizy: '.$events->count());

        $groupedByLegacyPurchaser = LegacyEvent::query()
            ->whereNotNull('legacy_purchaser_id')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('client_name')
                    ->orWhereNotNull('client_email')
                    ->orWhereNotNull('client_phone')
                    ->orWhereNotNull('client_contact_person')
                    ->orWhereNotNull('client_city')
                    ->orWhereNotNull('client_street')
                    ->orWhereNotNull('client_nip');
            })
            ->get()
            ->groupBy('legacy_purchaser_id');

        $contractors = Contractor::query()
            ->select(['id', 'name', 'firstname', 'surname', 'email', 'phone', 'street', 'city', 'nip'])
            ->get();

        $contractorByName = $contractors
            ->filter(fn (Contractor $contractor) => filled($contractor->name))
            ->mapWithKeys(fn (Contractor $contractor) => [$this->normalizeName($contractor->name) => $contractor]);

        $contractorByEmail = $contractors
            ->filter(fn (Contractor $contractor) => filled($contractor->email))
            ->mapWithKeys(fn (Contractor $contractor) => [$this->normalizeEmail($contractor->email) => $contractor]);

        $contractorByPhone = $contractors
            ->filter(fn (Contractor $contractor) => filled($contractor->phone))
            ->mapWithKeys(fn (Contractor $contractor) => [$this->normalizePhone($contractor->phone) => $contractor]);

        $updated = 0;
        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        foreach ($events as $event) {
            $bar->advance();
            $changes = [];

            $donor = $this->findDonorEvent($event, $groupedByLegacyPurchaser);
            if ($donor !== null) {
                $changes = array_merge($changes, $this->collectMissingFieldsFromDonor($event, $donor));
            }

            $matchedContractor = $this->findBestContractorMatch(
                $event,
                $changes,
                $contractorByName,
                $contractorByEmail,
                $contractorByPhone,
            );

            if ($matchedContractor !== null && empty($changes['contractor_id'])) {
                $changes['contractor_id'] = $matchedContractor->id;
            }

            if ($matchedContractor !== null) {
                $changes = array_merge($changes, $this->collectMissingFieldsFromContractor($event, $changes, $matchedContractor));
            }

            $changes = array_merge($changes, $this->collectMissingFieldsFromNotes($event, $changes));

            if ($changes === []) {
                continue;
            }

            if (! $dryRun) {
                $event->fill($changes);
                $event->save();
            }

            $updated++;
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("[DRY-RUN] Rekordy do aktualizacji: {$updated}");
        } else {
            $this->info("Zaktualizowane rekordy: {$updated}");
        }

        return Command::SUCCESS;
    }

    private function findDonorEvent(LegacyEvent $event, Collection $groupedByLegacyPurchaser): ?LegacyEvent
    {
        if (blank($event->legacy_purchaser_id)) {
            return null;
        }

        /** @var Collection<int, LegacyEvent>|null $group */
        $group = $groupedByLegacyPurchaser->get($event->legacy_purchaser_id);
        if ($group === null || $group->isEmpty()) {
            return null;
        }

        return $group
            ->filter(fn (LegacyEvent $candidate) => $candidate->id !== $event->id)
            ->sortByDesc(fn (LegacyEvent $candidate) => $this->countFilledPurchaserFields($candidate))
            ->first();
    }

    private function collectMissingFieldsFromDonor(LegacyEvent $event, LegacyEvent $donor): array
    {
        $fields = [
            'client_name',
            'client_email',
            'client_phone',
            'client_contact_person',
            'client_city',
            'client_street',
            'client_nip',
        ];

        $changes = [];
        foreach ($fields as $field) {
            if (blank($event->{$field}) && filled($donor->{$field})) {
                $changes[$field] = $donor->{$field};
            }
        }

        return $changes;
    }

    private function collectMissingFieldsFromContractor(LegacyEvent $event, array $pendingChanges, Contractor $contractor): array
    {
        $existing = fn (string $field) => $pendingChanges[$field] ?? $event->{$field};

        $contactPerson = trim((string) ($contractor->firstname.' '.$contractor->surname));

        $changes = [];

        if (blank($existing('client_name')) && filled($contractor->name)) {
            $changes['client_name'] = $contractor->name;
        }

        if ($this->shouldBackfillEmail($existing('client_email')) && filled($contractor->email)) {
            $changes['client_email'] = $contractor->email;
        }

        if (blank($existing('client_phone')) && filled($contractor->phone)) {
            $changes['client_phone'] = $contractor->phone;
        }

        if (blank($existing('client_street')) && filled($contractor->street)) {
            $changes['client_street'] = $contractor->street;
        }

        if (blank($existing('client_city')) && filled($contractor->city)) {
            $changes['client_city'] = $contractor->city;
        }

        if (blank($existing('client_nip')) && filled($contractor->nip)) {
            $changes['client_nip'] = $contractor->nip;
        }

        if (blank($existing('client_contact_person')) && filled($contactPerson)) {
            $changes['client_contact_person'] = $contactPerson;
        }

        return $changes;
    }

    private function findBestContractorMatch(
        LegacyEvent $event,
        array $pendingChanges,
        Collection $contractorByName,
        Collection $contractorByEmail,
        Collection $contractorByPhone,
    ): ?Contractor {
        if (filled($pendingChanges['contractor_id'] ?? $event->contractor_id)) {
            return Contractor::find($pendingChanges['contractor_id'] ?? $event->contractor_id);
        }

        $name = $this->normalizeName($pendingChanges['client_name'] ?? $event->client_name);
        if (filled($name) && $contractorByName->has($name)) {
            return $contractorByName->get($name);
        }

        $email = $this->normalizeEmail($pendingChanges['client_email'] ?? $event->client_email);
        if (filled($email) && $contractorByEmail->has($email)) {
            return $contractorByEmail->get($email);
        }

        $phone = $this->normalizePhone($pendingChanges['client_phone'] ?? $event->client_phone);
        if (filled($phone) && $contractorByPhone->has($phone)) {
            return $contractorByPhone->get($phone);
        }

        return null;
    }

    private function collectMissingFieldsFromNotes(LegacyEvent $event, array $pendingChanges): array
    {
        $existing = fn (string $field) => $pendingChanges[$field] ?? $event->{$field};
        $sourceText = strip_tags(implode("\n", array_filter([
            $event->order_note,
            $event->notes,
            $event->pilot_notes,
        ])));

        if (blank($sourceText)) {
            return [];
        }

        $changes = [];

        if ($this->shouldBackfillEmail($existing('client_email'))) {
            $email = $this->extractEmailFromText($sourceText);
            if (filled($email)) {
                $changes['client_email'] = $email;
            }
        }

        if (blank($existing('client_phone'))) {
            $phone = $this->extractPhoneFromText($sourceText);
            if (filled($phone)) {
                $changes['client_phone'] = $phone;
            }
        }

        if (blank($existing('client_contact_person'))) {
            $contact = $this->extractContactFromText($sourceText);
            if (filled($contact)) {
                $changes['client_contact_person'] = $contact;
            }
        }

        return $changes;
    }

    private function normalizeEmail(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strtolower(trim($value));
    }

    private function normalizeName(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    private function normalizePhone(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $value);

        return filled($digitsOnly) ? $digitsOnly : null;
    }

    private function extractEmailFromText(string $text): ?string
    {
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,10}(?![a-z])/i', $text, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $normalized = $this->normalizeExtractedEmail($candidate);
            if ($this->isValidEmail($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function extractPhoneFromText(string $text): ?string
    {
        preg_match('/(?:\+?\d[\d\s\-()]{7,}\d)/', $text, $matches);

        if (! isset($matches[0])) {
            return null;
        }

        return $this->normalizePhone($matches[0]);
    }

    private function extractContactFromText(string $text): ?string
    {
        preg_match('/(?:Imię i nazwisko|Pozdrawiam\s+|To:)\s*([A-ZŁŚŻŹĆŃÓ][\p{L}]+(?:\s+[A-ZŁŚŻŹĆŃÓ][\p{L}]+){0,2})/u', $text, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    private function countFilledPurchaserFields(LegacyEvent $event): int
    {
        $fields = [
            $event->client_name,
            $event->client_email,
            $event->client_phone,
            $event->client_contact_person,
            $event->client_city,
            $event->client_street,
            $event->client_nip,
        ];

        return collect($fields)->filter(fn ($value) => filled($value))->count();
    }

    private function shouldBackfillEmail(?string $email): bool
    {
        return ! $this->isValidEmail($email);
    }

    private function isValidEmail(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return ! (bool) preg_match('/\.(com|net|org|pl|edu|gov|info|biz|eu)[a-z]$/i', $email);
    }

    private function normalizeExtractedEmail(string $value): ?string
    {
        $normalized = $this->normalizeEmail($value);
        if (blank($normalized)) {
            return null;
        }

        if (preg_match('/^(.+\.(com|net|org|pl|edu|gov|info|biz|eu))[a-z]+$/i', $normalized, $matches)) {
            return strtolower($matches[1]);
        }

        return $normalized;
    }
}
