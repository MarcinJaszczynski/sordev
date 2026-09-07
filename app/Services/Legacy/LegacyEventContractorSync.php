<?php

namespace App\Services\Legacy;

use App\Models\LegacyEvent;
use App\Support\LegacyContractorLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Synchronizuje udział kontrahentów w imprezach archiwalnych (zamawiający + wykonawcy z JSON).
 */
class LegacyEventContractorSync
{
    /**
     * @return array{synced_events: int, links: int}
     */
    public function syncAll(?callable $onProgress = null): array
    {
        if (! Schema::hasTable('contractor_legacy_event')) {
            return ['synced_events' => 0, 'links' => 0];
        }

        $synced = 0;
        $links = 0;

        LegacyEvent::query()
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$synced, &$links, $onProgress): void {
                foreach ($events as $event) {
                    $links += $this->syncEvent($event);
                    $synced++;
                    if ($onProgress) {
                        $onProgress($synced);
                    }
                }
            });

        return ['synced_events' => $synced, 'links' => $links];
    }

    public function syncEvent(LegacyEvent $event): int
    {
        if (! Schema::hasTable('contractor_legacy_event')) {
            return 0;
        }

        /** @var array<int, array{role: string, type_name: ?string}> $byContractor */
        $byContractor = [];

        if (filled($event->contractor_id)) {
            $byContractor[(int) $event->contractor_id] = [
                'role' => 'purchaser',
                'type_name' => 'klient',
            ];
        }

        foreach ($this->decodeList($event->contractors_json) as $row) {
            $currentId = $this->resolveCurrentId($row['contractor_id'] ?? null);
            if ($currentId === null) {
                continue;
            }
            $type = $row['type_name'] ?? null;
            if (! isset($byContractor[$currentId]) || $byContractor[$currentId]['role'] !== 'purchaser') {
                $byContractor[$currentId] = [
                    'role' => 'executor',
                    'type_name' => is_string($type) ? $type : null,
                ];
            }
        }

        foreach ($this->decodeList($event->payments_json) as $row) {
            $currentId = $this->resolveCurrentId($row['contractor_id'] ?? null);
            if ($currentId === null || isset($byContractor[$currentId])) {
                continue;
            }
            $byContractor[$currentId] = [
                'role' => 'payment',
                'type_name' => isset($row['type_name']) && is_string($row['type_name']) ? $row['type_name'] : null,
            ];
        }

        foreach ($this->decodeList($event->elements_json) as $row) {
            $currentId = $this->resolveCurrentId($row['contractor_id'] ?? null);
            if ($currentId === null || isset($byContractor[$currentId])) {
                continue;
            }
            $byContractor[$currentId] = [
                'role' => 'executor',
                'type_name' => null,
            ];
        }

        DB::table('contractor_legacy_event')
            ->where('legacy_event_id', $event->id)
            ->delete();

        if ($byContractor === []) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($byContractor as $contractorId => $meta) {
            $rows[] = [
                'contractor_id' => $contractorId,
                'legacy_event_id' => $event->id,
                'role' => $meta['role'],
                'type_name' => $meta['type_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('contractor_legacy_event')->insert($chunk);
        }

        return count($rows);
    }

    private function resolveCurrentId(mixed $legacyOrCurrentId): ?int
    {
        if ($legacyOrCurrentId === null || $legacyOrCurrentId === '') {
            return null;
        }

        return LegacyContractorLookup::currentId((int) $legacyOrCurrentId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
