<?php

namespace App\Services\Legacy;

use App\Models\Contact;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\LegacyEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OldSorImportService
{
    public function __construct(
        private readonly OldSorSqlDumpReader $reader,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     source: string,
     *     contractor_types: array{create: int, skip: int},
     *     contractors: array{create: int, match_legacy_id: int, match_nip: int, match_name: int, set_legacy_id: int, samples_create: list<string>},
     *     type_links: array{attach: int},
     *     contacts: array{create: int},
     *     legacy_events: array{
     *         create: int,
     *         skip: int,
     *         link_contractor: int,
     *         enrich_json: int,
     *         by_status_create: array<string, int>,
     *         samples_create: list<string>
     *     }
     * }
     */
    public function import(string $sqlPath, bool $dryRun = true): array
    {
        if (! is_readable($sqlPath)) {
            throw new \InvalidArgumentException("Nie można odczytać pliku: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);
        if ($sql === false || $sql === '') {
            throw new \InvalidArgumentException("Pusty lub nieczytelny dump: {$sqlPath}");
        }

        $types = $this->reader->readTable($sql, 'contractor_types')['rows'];
        $contractors = $this->reader->readTable($sql, 'contractors')['rows'];
        $typePivot = $this->reader->readTable($sql, 'contractor_contractortype')['rows'];
        $events = $this->reader->readTable($sql, 'events')['rows'];
        $elements = $this->reader->readTable($sql, 'event_elements')['rows'];
        $payments = $this->reader->readTable($sql, 'event_payments')['rows'];
        $eventContractors = $this->reader->readTable($sql, 'event_contractors')['rows'];
        $notes = $this->reader->readTable($sql, 'notes')['rows'];

        $elementsByEvent = $this->groupByIntKey($elements, 'eventIdinEventElements');
        $paymentsByEvent = $this->groupByIntKey($payments, 'event_id');
        $eventContractorsByEvent = $this->groupByIntKey($eventContractors, 'event_id');
        $notesByEvent = $this->groupByIntKey($notes, 'event_id');

        $contractorNameByLegacyId = [];
        foreach ($contractors as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $contractorNameByLegacyId[$id] = (string) ($row['name'] ?? '');
            }
        }

        $typeNameByLegacyId = [];
        foreach ($types as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $typeNameByLegacyId[$id] = (string) ($row['name'] ?? '');
            }
        }

        $report = [
            'dry_run' => $dryRun,
            'source' => $sqlPath,
            'contractor_types' => ['create' => 0, 'skip' => 0],
            'contractors' => [
                'create' => 0,
                'match_legacy_id' => 0,
                'match_nip' => 0,
                'match_name' => 0,
                'set_legacy_id' => 0,
                'samples_create' => [],
            ],
            'type_links' => ['attach' => 0],
            'contacts' => ['create' => 0],
            'legacy_events' => [
                'create' => 0,
                'skip' => 0,
                'link_contractor' => 0,
                'enrich_json' => 0,
                'by_status_create' => [],
                'samples_create' => [],
            ],
        ];

        $run = function () use (
            &$report,
            $dryRun,
            $types,
            $contractors,
            $typePivot,
            $events,
            $elementsByEvent,
            $paymentsByEvent,
            $eventContractorsByEvent,
            $notesByEvent,
            $contractorNameByLegacyId,
            $typeNameByLegacyId,
        ): void {
            /** @var array<int, int> $legacyTypeIdToCurrent */
            $legacyTypeIdToCurrent = $this->syncTypes($types, $dryRun, $report);

            /** @var array<int, int> $legacyContractorIdToCurrent */
            $legacyContractorIdToCurrent = $this->syncContractors($contractors, $dryRun, $report);

            $this->syncTypeLinks($typePivot, $legacyTypeIdToCurrent, $legacyContractorIdToCurrent, $dryRun, $report);

            $this->syncLegacyEvents(
                $events,
                $legacyContractorIdToCurrent,
                $elementsByEvent,
                $paymentsByEvent,
                $eventContractorsByEvent,
                $notesByEvent,
                $contractorNameByLegacyId,
                $typeNameByLegacyId,
                $dryRun,
                $report,
            );
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
            if (Schema::hasTable('contractor_legacy_event')) {
                $pivot = app(LegacyEventContractorSync::class)->syncAll();
                $report['pivot_links'] = $pivot['links'];
            }
        }

        return $report;
    }

    /**
     * @param  list<array<string, mixed>>  $types
     * @param  array<string, mixed>  $report
     * @return array<int, int>
     */
    private function syncTypes(array $types, bool $dryRun, array &$report): array
    {
        $existingByName = ContractorType::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ContractorType $t) => [$this->normalizeName($t->name) => (int) $t->id])
            ->all();

        $map = [];

        foreach ($types as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($legacyId <= 0 || $name === '') {
                continue;
            }

            $key = $this->normalizeName($name);
            if (isset($existingByName[$key])) {
                $map[$legacyId] = $existingByName[$key];
                $report['contractor_types']['skip']++;

                continue;
            }

            $report['contractor_types']['create']++;
            if ($dryRun) {
                $map[$legacyId] = -1 * $legacyId;

                continue;
            }

            $type = ContractorType::query()->create(['name' => $name]);
            $existingByName[$key] = (int) $type->id;
            $map[$legacyId] = (int) $type->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $contractors
     * @param  array<string, mixed>  $report
     * @return array<int, int>
     */
    private function syncContractors(array $contractors, bool $dryRun, array &$report): array
    {
        $hasLegacyCol = Schema::hasColumn('contractors', 'legacy_contractor_id');

        $query = Contractor::query()->withTrashed();
        if ($hasLegacyCol) {
            $query->select(['id', 'name', 'nip', 'email', 'phone', 'firstname', 'surname', 'legacy_contractor_id', 'deleted_at']);
        } else {
            $query->select(['id', 'name', 'nip', 'email', 'phone', 'firstname', 'surname', 'deleted_at']);
        }

        $existing = $query->get();

        /** @var array<int, int> $byLegacyId */
        $byLegacyId = [];
        /** @var array<string, int> $byNip */
        $byNip = [];
        /** @var array<string, int> $byName */
        $byName = [];

        foreach ($existing as $c) {
            if ($hasLegacyCol && filled($c->legacy_contractor_id)) {
                $byLegacyId[(int) $c->legacy_contractor_id] = (int) $c->id;
            }
            $nip = $this->normalizeNip($c->nip);
            if ($nip !== null) {
                $byNip[$nip] = (int) $c->id;
            }
            $name = $this->normalizeName($c->name);
            if ($name !== '') {
                $byName[$name] = (int) $c->id;
            }
        }

        $map = [];
        $contactPivot = Contractor::contactPivotTable();

        foreach ($contractors as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $nip = $this->normalizeNip($row['nip'] ?? null);
            $matchedId = null;
            $matchKind = null;

            if (isset($byLegacyId[$legacyId])) {
                $matchedId = $byLegacyId[$legacyId];
                $matchKind = 'match_legacy_id';
            } elseif ($nip !== null && isset($byNip[$nip])) {
                $matchedId = $byNip[$nip];
                $matchKind = 'match_nip';
            } elseif (isset($byName[$this->normalizeName($name)])) {
                $matchedId = $byName[$this->normalizeName($name)];
                $matchKind = 'match_name';
            }

            if ($matchedId !== null) {
                $report['contractors'][$matchKind]++;
                $map[$legacyId] = $matchedId;

                if ($hasLegacyCol && $matchKind !== 'match_legacy_id') {
                    $report['contractors']['set_legacy_id']++;
                    if (! $dryRun) {
                        Contractor::withTrashed()
                            ->whereKey($matchedId)
                            ->whereNull('legacy_contractor_id')
                            ->update(['legacy_contractor_id' => $legacyId]);
                    }
                    $byLegacyId[$legacyId] = $matchedId;
                }

                continue;
            }

            $report['contractors']['create']++;
            if (count($report['contractors']['samples_create']) < 20) {
                $report['contractors']['samples_create'][] = $name.($nip ? " (NIP {$nip})" : '');
            }

            if ($dryRun) {
                $this->maybeCreateContactFromContractorRow($row, true, $report);
                $map[$legacyId] = -1 * $legacyId;

                continue;
            }

            $payload = [
                'name' => $name,
                'street' => $this->nullableString($row['street'] ?? null),
                'city' => $this->nullableString($row['city'] ?? null),
                'region' => $this->nullableString($row['region'] ?? null),
                'country' => $this->nullableString($row['country'] ?? null),
                'nip' => $this->nullableString($row['nip'] ?? null),
                'phone' => $this->nullableString($row['phone'] ?? null),
                'email' => $this->nullableString($row['email'] ?? null),
                'www' => $this->nullableString($row['www'] ?? null),
                'description' => $this->nullableString($row['description'] ?? null),
                'firstname' => $this->nullableString($row['firstname'] ?? null),
                'surname' => $this->nullableString($row['surname'] ?? null),
                'status' => 'active',
            ];
            if ($hasLegacyCol) {
                $payload['legacy_contractor_id'] = $legacyId;
            }

            $contractor = Contractor::query()->create($payload);
            $currentId = (int) $contractor->id;
            $map[$legacyId] = $currentId;
            $byLegacyId[$legacyId] = $currentId;
            if ($nip !== null) {
                $byNip[$nip] = $currentId;
            }
            $byName[$this->normalizeName($name)] = $currentId;

            $contactId = $this->maybeCreateContactFromContractorRow($row, false, $report);
            if ($contactId !== null && Schema::hasTable($contactPivot)) {
                DB::table($contactPivot)->insertOrIgnore([
                    'contractor_id' => $currentId,
                    'contact_id' => $contactId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $report
     */
    private function maybeCreateContactFromContractorRow(array $row, bool $dryRun, array &$report): ?int
    {
        $first = trim((string) ($row['firstname'] ?? ''));
        $last = trim((string) ($row['surname'] ?? ''));
        $phone = $this->nullableString($row['phone'] ?? null);
        $email = $this->nullableString($row['email'] ?? null);
        $name = trim((string) ($row['name'] ?? 'kontrahent'));

        if ($first === '' && $last === '') {
            $first = 'Kontakt';
            $last = mb_substr($name !== '' ? $name : 'kontrahent', 0, 255);
        } elseif ($last === '') {
            $last = '—';
        } elseif ($first === '') {
            $first = '—';
        }

        $report['contacts']['create']++;
        if ($dryRun) {
            return null;
        }

        $contact = Contact::query()->create([
            'first_name' => mb_substr($first, 0, 255),
            'last_name' => mb_substr($last, 0, 255),
            'phone' => $phone,
            'email' => $email,
        ]);

        return (int) $contact->id;
    }

    /**
     * @param  list<array<string, mixed>>  $pivot
     * @param  array<int, int>  $legacyTypeIdToCurrent
     * @param  array<int, int>  $legacyContractorIdToCurrent
     * @param  array<string, mixed>  $report
     */
    private function syncTypeLinks(
        array $pivot,
        array $legacyTypeIdToCurrent,
        array $legacyContractorIdToCurrent,
        bool $dryRun,
        array &$report,
    ): void {
        $existingPairs = DB::table('contractor_contractortype')
            ->get(['contractor_id', 'contractor_type_id'])
            ->mapWithKeys(fn ($r) => [((int) $r->contractor_id).':'.((int) $r->contractor_type_id) => true])
            ->all();

        foreach ($pivot as $row) {
            $legacyContractorId = (int) ($row['contractor_id'] ?? 0);
            $legacyTypeId = (int) ($row['contractor_type_id'] ?? 0);
            $currentContractorId = $legacyContractorIdToCurrent[$legacyContractorId] ?? null;
            $currentTypeId = $legacyTypeIdToCurrent[$legacyTypeId] ?? null;

            if ($currentContractorId === null || $currentTypeId === null) {
                continue;
            }

            // dry-run: nowe kontrahenty/typy mają ujemne ID — i tak policz attach
            if ($currentContractorId < 0 || $currentTypeId < 0) {
                $report['type_links']['attach']++;

                continue;
            }

            $key = $currentContractorId.':'.$currentTypeId;
            if (isset($existingPairs[$key])) {
                continue;
            }

            $report['type_links']['attach']++;
            $existingPairs[$key] = true;

            if ($dryRun) {
                continue;
            }

            DB::table('contractor_contractortype')->insert([
                'contractor_id' => $currentContractorId,
                'contractor_type_id' => $currentTypeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<int, int>  $legacyContractorIdToCurrent
     * @param  array<int, list<array<string, mixed>>>  $elementsByEvent
     * @param  array<int, list<array<string, mixed>>>  $paymentsByEvent
     * @param  array<int, list<array<string, mixed>>>  $eventContractorsByEvent
     * @param  array<int, list<array<string, mixed>>>  $notesByEvent
     * @param  array<int, string>  $contractorNameByLegacyId
     * @param  array<int, string>  $typeNameByLegacyId
     * @param  array<string, mixed>  $report
     */
    private function syncLegacyEvents(
        array $events,
        array $legacyContractorIdToCurrent,
        array $elementsByEvent,
        array $paymentsByEvent,
        array $eventContractorsByEvent,
        array $notesByEvent,
        array $contractorNameByLegacyId,
        array $typeNameByLegacyId,
        bool $dryRun,
        array &$report,
    ): void {
        $existing = LegacyEvent::query()
            ->get(['id', 'legacy_id', 'contractor_id', 'elements_json', 'payments_json', 'contractors_json', 'notes_json'])
            ->keyBy(fn (LegacyEvent $e) => (int) $e->legacy_id);

        foreach ($events as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $status = $this->nullableString($row['eventStatus'] ?? null) ?? 'brak';
            $payload = $this->mapEventRow(
                $row,
                $legacyContractorIdToCurrent,
                $elementsByEvent[$legacyId] ?? [],
                $paymentsByEvent[$legacyId] ?? [],
                $eventContractorsByEvent[$legacyId] ?? [],
                $notesByEvent[$legacyId] ?? [],
                $contractorNameByLegacyId,
                $typeNameByLegacyId,
            );

            /** @var LegacyEvent|null $existingEvent */
            $existingEvent = $existing->get($legacyId);

            if ($existingEvent === null) {
                $report['legacy_events']['create']++;
                $report['legacy_events']['by_status_create'][$status] = ($report['legacy_events']['by_status_create'][$status] ?? 0) + 1;
                if (count($report['legacy_events']['samples_create']) < 15) {
                    $code = $payload['office_id'] ?? '';
                    $report['legacy_events']['samples_create'][] = trim("#{$legacyId} {$code} — {$payload['name']} [{$status}]");
                }

                if (! $dryRun) {
                    LegacyEvent::query()->create($payload);
                }

                continue;
            }

            $report['legacy_events']['skip']++;

            $updates = [];
            if (empty($existingEvent->contractor_id) && ! empty($payload['contractor_id'])) {
                $updates['contractor_id'] = $payload['contractor_id'];
                $report['legacy_events']['link_contractor']++;
            }

            $enrichUpdates = [];
            if ($this->jsonLooksEmpty($existingEvent->elements_json) && ! empty($payload['elements_json'])) {
                $enrichUpdates['elements_json'] = $payload['elements_json'];
            }
            if ($this->jsonLooksEmpty($existingEvent->payments_json) && ! empty($payload['payments_json'])) {
                $enrichUpdates['payments_json'] = $payload['payments_json'];
            }
            if ($this->jsonLooksEmpty($existingEvent->contractors_json) && ! empty($payload['contractors_json'])) {
                $enrichUpdates['contractors_json'] = $payload['contractors_json'];
            }
            if ($this->jsonLooksEmpty($existingEvent->notes_json) && ! empty($payload['notes_json'])) {
                $enrichUpdates['notes_json'] = $payload['notes_json'];
            }

            foreach (['legacy_purchaser_id', 'start_description', 'end_description', 'order_note', 'notes', 'pilot_notes', 'diet_alert'] as $field) {
                if (blank($existingEvent->{$field}) && filled($payload[$field] ?? null)) {
                    $enrichUpdates[$field] = $payload[$field];
                }
            }

            if ($enrichUpdates !== []) {
                $report['legacy_events']['enrich_json']++;
                $updates = array_merge($updates, $enrichUpdates);
            }

            if ($updates === [] || $dryRun) {
                continue;
            }

            $existingEvent->forceFill($updates)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $legacyContractorIdToCurrent
     * @param  list<array<string, mixed>>  $elements
     * @param  list<array<string, mixed>>  $payments
     * @param  list<array<string, mixed>>  $eventContractors
     * @param  list<array<string, mixed>>  $notes
     * @param  array<int, string>  $contractorNameByLegacyId
     * @param  array<int, string>  $typeNameByLegacyId
     * @return array<string, mixed>
     */
    private function mapEventRow(
        array $row,
        array $legacyContractorIdToCurrent,
        array $elements,
        array $payments,
        array $eventContractors,
        array $notes,
        array $contractorNameByLegacyId,
        array $typeNameByLegacyId,
    ): array {
        $legacyPurchaserId = isset($row['purchaser_id']) && $row['purchaser_id'] !== null
            ? (int) $row['purchaser_id']
            : null;

        $contractorId = null;
        if ($legacyPurchaserId && isset($legacyContractorIdToCurrent[$legacyPurchaserId]) && $legacyContractorIdToCurrent[$legacyPurchaserId] > 0) {
            $contractorId = $legacyContractorIdToCurrent[$legacyPurchaserId];
        }

        return [
            'legacy_id' => (int) $row['id'],
            'office_id' => $this->nullableString($row['eventOfficeId'] ?? null),
            'name' => trim((string) ($row['eventName'] ?? 'Bez nazwy')) ?: 'Bez nazwy',
            'legacy_status' => $this->nullableString($row['eventStatus'] ?? null),
            'start_datetime' => $this->parseDateTime($row['eventStartDateTime'] ?? null),
            'end_datetime' => $this->parseDateTime($row['eventEndDateTime'] ?? null),
            'duration_days' => $this->nullableInt($row['duration'] ?? null),
            'participant_count' => $this->nullableInt($row['eventTotalQty'] ?? null),
            'guardians_count' => $this->nullableInt($row['eventGuardiansQty'] ?? null),
            'free_count' => $this->nullableInt($row['eventFreeQty'] ?? null),
            'client_name' => $this->nullableString($row['eventPurchaserName'] ?? null),
            'client_street' => $this->nullableString($row['eventPurchaserStreet'] ?? null),
            'client_city' => $this->nullableString($row['eventPurchaserCity'] ?? null),
            'client_nip' => $this->nullableString($row['eventPurchaserNip'] ?? null),
            'client_contact_person' => $this->nullableString($row['eventPurchaserContactPerson'] ?? null),
            'client_phone' => $this->nullableString($row['eventPurchaserTel'] ?? null),
            'client_email' => $this->nullableString($row['eventPurchaserEmail'] ?? null),
            'pilot' => $this->nullableString($row['eventPilot'] ?? null),
            'driver' => $this->nullableString($row['eventDriver'] ?? null),
            'bus_board_time' => $this->parseDateTime($row['busBoardTime'] ?? null),
            'advance_payment' => $this->nullableInt($row['eventAdvancePayment'] ?? null),
            'notes' => $this->nullableString($row['eventNote'] ?? null),
            'start_description' => $this->nullableString($row['eventStartDescription'] ?? null),
            'end_description' => $this->nullableString($row['eventEndDescription'] ?? null),
            'diet_alert' => $this->nullableString($row['eventDietAlert'] ?? null),
            'pilot_notes' => $this->nullableString($row['eventPilotNotes'] ?? null),
            'order_note' => $this->nullableString($row['orderNote'] ?? null),
            'legacy_purchaser_id' => $legacyPurchaserId,
            'contractor_id' => $contractorId,
            'elements_json' => $this->mapElements($elements, $contractorNameByLegacyId),
            'contractors_json' => $this->mapEventContractors($eventContractors, $contractorNameByLegacyId, $typeNameByLegacyId),
            'payments_json' => $this->mapPayments($payments, $contractorNameByLegacyId, $typeNameByLegacyId),
            'notes_json' => $this->mapNotes($notes),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<int, string>  $contractorNameByLegacyId
     * @return list<array<string, mixed>>
     */
    private function mapElements(array $elements, array $contractorNameByLegacyId): array
    {
        $out = [];
        foreach ($elements as $el) {
            $cid = isset($el['contractor_id']) ? (int) $el['contractor_id'] : null;
            $out[] = [
                'id' => $el['id'] ?? null,
                'name' => $el['element_name'] ?? null,
                'description' => $el['eventElementDescription'] ?? null,
                'start' => $el['eventElementStart'] ?? null,
                'end' => $el['eventElementEnd'] ?? null,
                'cost' => $el['eventElementCost'] ?? null,
                'cost_status' => $el['eventElementCostStatus'] ?? null,
                'cost_payer' => $el['eventElementCostPayer'] ?? null,
                'qty' => $el['eventElementCostQty'] ?? null,
                'note' => $el['eventElementNote'] ?? null,
                'reservation' => $el['eventElementReservation'] ?? null,
                'invoice_no' => $el['eventElementInvoiceNo'] ?? null,
                'contact' => $el['eventElementContact'] ?? null,
                'contractor_id' => $cid,
                'contractor_name' => $cid ? ($contractorNameByLegacyId[$cid] ?? null) : null,
                'active' => $el['active'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $contractorNameByLegacyId
     * @param  array<int, string>  $typeNameByLegacyId
     * @return list<array<string, mixed>>
     */
    private function mapEventContractors(array $rows, array $contractorNameByLegacyId, array $typeNameByLegacyId): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cid = isset($row['contractor_id']) ? (int) $row['contractor_id'] : null;
            $tid = isset($row['contractortype_id']) ? (int) $row['contractortype_id'] : null;
            $out[] = [
                'id' => $row['id'] ?? null,
                'contractor_id' => $cid,
                'contractor_name' => $cid ? ($contractorNameByLegacyId[$cid] ?? null) : null,
                'type_id' => $tid,
                'type_name' => $tid ? ($typeNameByLegacyId[$tid] ?? null) : null,
                'element_id' => $row['eventelement_id'] ?? null,
                'desc' => $row['desc'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, string>  $contractorNameByLegacyId
     * @param  array<int, string>  $typeNameByLegacyId
     * @return list<array<string, mixed>>
     */
    private function mapPayments(array $rows, array $contractorNameByLegacyId, array $typeNameByLegacyId): array
    {
        $out = [];
        foreach ($rows as $row) {
            $cid = isset($row['contractor_id']) ? (int) $row['contractor_id'] : null;
            $tid = isset($row['contractortype_id']) ? (int) $row['contractortype_id'] : null;
            $out[] = [
                'id' => $row['id'] ?? null,
                'name' => $row['paymentName'] ?? null,
                'description' => $row['paymentDescription'] ?? null,
                'payer' => $row['payer'] ?? null,
                'status' => $row['paymentStatus'] ?? null,
                'date' => $row['paymentDate'] ?? null,
                'qty' => $row['qty'] ?? null,
                'price' => $row['price'] ?? null,
                'planned_qty' => $row['plannedQty'] ?? null,
                'planned_price' => $row['plannedPrice'] ?? null,
                'note' => $row['paymentNote'] ?? null,
                'advance' => $row['advance'] ?? null,
                'accepted' => $row['accepted'] ?? null,
                'invoice' => $row['invoice'] ?? null,
                'contractor_id' => $cid,
                'contractor_name' => $cid ? ($contractorNameByLegacyId[$cid] ?? null) : null,
                'type_id' => $tid,
                'type_name' => $tid ? ($typeNameByLegacyId[$tid] ?? null) : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapNotes(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['id'] ?? null,
                'name' => $row['name'] ?? null,
                'description' => $row['description'] ?? null,
                'element_id' => $row['event_element_id'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupByIntKey(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            $id = (int) $row[$key];
            $out[$id][] = $row;
        }

        return $out;
    }

    private function jsonLooksEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === 'null' || $value === '[]') {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    private function normalizeName(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizeNip(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return ($digits !== null && $digits !== '') ? $digits : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
