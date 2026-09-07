<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Synchronizuje katalog szablonów / punktów programu / cenników
 * z dumpem SQLite produkcji do bieżącego połączenia (MySQL).
 *
 * Nie rusza events, finance, contractors ani lokalnych-only kolumn schematu.
 */
final class ProdSqliteCatalogSync
{
    private const TEMPLATE_SCOPED_TABLES = [
        'event_template_event_template_program_point',
        'event_template_program_point_child_pivot',
        'event_template_hotel_days',
        'event_template_day_insurance',
        'event_template_event_price_description',
        'event_template_event_type',
        'event_template_tag',
        'event_template_tax',
        'event_template_transport_type',
        'event_template_starting_place_availability',
        'event_template_price_per_person',
    ];

    /** @var array<string, list<string>> */
    private const DEDUPE_KEYS = [
        'event_template_day_insurance' => ['event_template_id', 'day', 'insurance_id'],
        'event_template_hotel_days' => ['event_template_id', 'day'],
        'event_template_price_per_person' => ['event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id'],
        'event_template_program_point_child_pivot' => ['event_template_id', 'program_point_child_id'],
        'event_template_tax' => ['event_template_id', 'tax_id'],
        'event_template_transport_type' => ['event_template_id', 'transport_type_id'],
        'event_template_event_type' => ['event_template_id', 'event_type_id'],
        'event_template_program_point_parent' => ['parent_id', 'child_id'],
        'event_template_program_point_tag' => ['event_template_program_point_id', 'tag_id'],
        'event_template_tag' => ['event_template_id', 'tag_id'],
    ];

    private PDO $sqlite;

    /** @var array<string, list<string>> */
    private array $commonColumns = [];

    /** @var list<int> */
    private array $prodTemplateIds = [];

    /** @var list<int> */
    private array $prodProgramPointIds = [];

    /** @var array<int, true> */
    private array $localPlaceIds = [];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly string $sqlitePath,
        private readonly bool $dryRun = false,
        private readonly bool $skipPrices = false,
    ) {
        if (! is_file($sqlitePath)) {
            throw new RuntimeException("Brak pliku SQLite: {$sqlitePath}");
        }

        $this->sqlite = new PDO('sqlite:'.$sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @return array{stats: array<string, int>, warnings: list<string>, dry_run: bool}
     */
    public function run(): array
    {
        $this->bootstrapIds();

        if ($this->dryRun) {
            $this->dryRunReport();

            return [
                'stats' => $this->stats,
                'warnings' => $this->warnings,
                'dry_run' => true,
            ];
        }

        DB::connection()->disableQueryLog();

        DB::transaction(function (): void {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                $this->syncTags();
                $this->syncProgramPoints();
                $this->syncProgramPointTags();
                $this->syncProgramPointParents();
                $this->resolveTemplateSlugConflicts();
                $this->syncTemplates();
                $this->replaceTemplateScopedTables();
                $this->syncHotelRoomPrices();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return [
            'stats' => $this->stats,
            'warnings' => $this->warnings,
            'dry_run' => false,
        ];
    }

    private function bootstrapIds(): void
    {
        $this->prodTemplateIds = array_map(
            'intval',
            $this->sqlite->query('SELECT id FROM event_templates ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );
        $this->prodProgramPointIds = array_map(
            'intval',
            $this->sqlite->query('SELECT id FROM event_template_program_points ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)
        );

        foreach (DB::table('places')->pluck('id') as $id) {
            $this->localPlaceIds[(int) $id] = true;
        }
    }

    private function dryRunReport(): void
    {
        $localTagIds = DB::table('tags')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $localTagSet = array_fill_keys($localTagIds, true);
        $missingTags = 0;
        foreach ($this->sqlite->query('SELECT id FROM tags') as $row) {
            if (! isset($localTagSet[(int) $row['id']])) {
                $missingTags++;
            }
        }
        $this->stats['tags_insert'] = $missingTags;

        $localPp = DB::table('event_template_program_points')->pluck('id', 'id')->all();
        $ppInsert = 0;
        $ppUpdate = 0;
        foreach ($this->sqlite->query('SELECT id, name, unit_price, updated_at FROM event_template_program_points') as $row) {
            $id = (int) $row['id'];
            if (! isset($localPp[$id])) {
                $ppInsert++;

                continue;
            }
            $local = DB::table('event_template_program_points')->where('id', $id)->first(['name', 'unit_price', 'updated_at']);
            if (! $local) {
                $ppInsert++;

                continue;
            }
            if ((string) $local->name !== (string) $row['name']
                || abs((float) $local->unit_price - (float) $row['unit_price']) > 0.0001
                || (string) $local->updated_at !== (string) $row['updated_at']
            ) {
                $ppUpdate++;
            }
        }
        $this->stats['program_points_insert'] = $ppInsert;
        $this->stats['program_points_update_candidates'] = $ppUpdate;

        $localTpl = DB::table('event_templates')->pluck('id', 'id')->all();
        $tplInsert = 0;
        $tplUpdate = 0;
        foreach ($this->prodTemplateIds as $id) {
            if (! isset($localTpl[$id])) {
                $tplInsert++;
            } else {
                $tplUpdate++;
            }
        }
        $this->stats['templates_insert'] = $tplInsert;
        $this->stats['templates_upsert'] = $tplUpdate;

        foreach (self::TEMPLATE_SCOPED_TABLES as $table) {
            if ($this->skipPrices && $table === 'event_template_price_per_person') {
                continue;
            }
            $localCount = DB::table($table)->whereIn('event_template_id', $this->prodTemplateIds)->count();
            $prodCount = (int) $this->sqlite->query(
                'SELECT COUNT(*) FROM '.$table.' WHERE event_template_id IN ('.$this->idListSql($this->prodTemplateIds).')'
            )->fetchColumn();
            $this->stats[$table.'_local_delete'] = $localCount;
            $this->stats[$table.'_prod_insert'] = $prodCount;
        }

        $this->stats['program_point_tags_local_delete'] = DB::table('event_template_program_point_tag')
            ->whereIn('event_template_program_point_id', $this->prodProgramPointIds)
            ->count();
        $this->stats['program_point_tags_prod_insert'] = (int) $this->sqlite->query(
            'SELECT COUNT(*) FROM event_template_program_point_tag WHERE event_template_program_point_id IN ('.$this->idListSql($this->prodProgramPointIds).')'
        )->fetchColumn();

        $this->stats['program_point_parents_local_delete'] = DB::table('event_template_program_point_parent')->count();
        $this->stats['program_point_parents_prod_insert'] = (int) $this->sqlite->query(
            'SELECT COUNT(*) FROM event_template_program_point_parent'
        )->fetchColumn();

        $hotelDiff = 0;
        foreach ($this->sqlite->query('SELECT id, price FROM hotel_rooms') as $row) {
            $local = DB::table('hotel_rooms')->where('id', (int) $row['id'])->value('price');
            if ($local === null) {
                continue;
            }
            if (abs((float) $local - (float) $row['price']) > 0.01) {
                $hotelDiff++;
            }
        }
        $this->stats['hotel_rooms_price_update'] = $hotelDiff;

        $orphanPrices = 0;
        foreach ($this->sqlite->query('SELECT start_place_id FROM event_template_price_per_person') as $row) {
            $placeId = $row['start_place_id'];
            if ($placeId !== null && ! isset($this->localPlaceIds[(int) $placeId])) {
                $orphanPrices++;
            }
        }
        if ($orphanPrices > 0) {
            $this->warnings[] = "Pominięte wiersze cennika z brakującym place_id: {$orphanPrices}";
            $this->stats['prices_skipped_orphan_place'] = $orphanPrices;
        }
    }

    private function syncTags(): void
    {
        $cols = $this->commonColumns('tags');
        $insert = 0;
        $existing = array_fill_keys(
            DB::table('tags')->pluck('id')->map(fn ($id) => (int) $id)->all(),
            true
        );

        foreach ($this->sqlite->query('SELECT * FROM tags') as $row) {
            $id = (int) $row['id'];
            if (isset($existing[$id])) {
                continue;
            }
            DB::table('tags')->insert($this->pickRow($row, $cols, 'tags'));
            $insert++;
        }

        $this->stats['tags_insert'] = $insert;
    }

    private function syncProgramPoints(): void
    {
        $cols = $this->commonColumns('event_template_program_points');
        $insert = 0;
        $update = 0;

        foreach ($this->sqlite->query('SELECT * FROM event_template_program_points') as $row) {
            $id = (int) $row['id'];
            $payload = $this->pickRow($row, $cols, 'event_template_program_points');
            $exists = DB::table('event_template_program_points')->where('id', $id)->exists();
            if ($exists) {
                unset($payload['id']);
                DB::table('event_template_program_points')->where('id', $id)->update($payload);
                $update++;
            } else {
                DB::table('event_template_program_points')->insert($payload);
                $insert++;
            }
        }

        $this->stats['program_points_insert'] = $insert;
        $this->stats['program_points_update'] = $update;
    }

    private function syncProgramPointTags(): void
    {
        $deleted = DB::table('event_template_program_point_tag')
            ->whereIn('event_template_program_point_id', $this->prodProgramPointIds)
            ->delete();
        $this->stats['program_point_tags_deleted'] = $deleted;

        $cols = $this->commonColumns('event_template_program_point_tag');
        $inserted = $this->streamInsert(
            'event_template_program_point_tag',
            'SELECT * FROM event_template_program_point_tag WHERE event_template_program_point_id IN ('.$this->idListSql($this->prodProgramPointIds).')',
            $cols,
            fn (array $row): bool => DB::table('tags')->where('id', (int) $row['tag_id'])->exists()
        );
        $this->stats['program_point_tags_inserted'] = $inserted;
    }

    private function syncProgramPointParents(): void
    {
        $deleted = DB::table('event_template_program_point_parent')->delete();
        $this->stats['program_point_parents_deleted'] = $deleted;

        $cols = $this->commonColumns('event_template_program_point_parent');
        $inserted = $this->streamInsert(
            'event_template_program_point_parent',
            'SELECT * FROM event_template_program_point_parent',
            $cols
        );
        $this->stats['program_point_parents_inserted'] = $inserted;
    }

    private function resolveTemplateSlugConflicts(): void
    {
        $renamed = 0;

        foreach ($this->sqlite->query('SELECT id, slug FROM event_templates') as $row) {
            $prodId = (int) $row['id'];
            $slug = (string) $row['slug'];
            if ($slug === '') {
                continue;
            }

            $blockers = DB::table('event_templates')
                ->where('slug', $slug)
                ->where('id', '!=', $prodId)
                ->pluck('id');

            foreach ($blockers as $blockerId) {
                $newSlug = $slug.'-local-'.$blockerId;
                // uniknij kolizji z już istniejącym -local-
                $suffix = 1;
                while (DB::table('event_templates')->where('slug', $newSlug)->exists()) {
                    $newSlug = $slug.'-local-'.$blockerId.'-'.$suffix;
                    $suffix++;
                }
                DB::table('event_templates')->where('id', (int) $blockerId)->update([
                    'slug' => $newSlug,
                    'updated_at' => now(),
                ]);
                $renamed++;
            }
        }

        $this->stats['template_slugs_freed'] = $renamed;
    }

    private function syncTemplates(): void
    {
        $cols = $this->commonColumns('event_templates');
        $insert = 0;
        $update = 0;

        foreach ($this->sqlite->query('SELECT * FROM event_templates') as $row) {
            $id = (int) $row['id'];
            $payload = $this->pickRow($row, $cols, 'event_templates');
            $payload['slug'] = $this->uniqueTemplateSlug((string) ($payload['slug'] ?? ''), $id);
            $exists = DB::table('event_templates')->where('id', $id)->exists();
            if ($exists) {
                unset($payload['id']);
                DB::table('event_templates')->where('id', $id)->update($payload);
                $update++;
            } else {
                DB::table('event_templates')->insert($payload);
                $insert++;
            }
        }

        $this->stats['templates_insert'] = $insert;
        $this->stats['templates_update'] = $update;
    }

    private function uniqueTemplateSlug(string $slug, int $id): string
    {
        if ($slug === '') {
            $slug = 'template-'.$id;
        }

        $candidate = $slug;
        $suffix = 0;
        while (
            DB::table('event_templates')
                ->where('slug', $candidate)
                ->where('id', '!=', $id)
                ->exists()
        ) {
            $suffix++;
            $candidate = $slug.'-id-'.$id.($suffix > 1 ? '-'.$suffix : '');
        }

        if ($candidate !== $slug) {
            $this->stats['template_slugs_deduped'] = ($this->stats['template_slugs_deduped'] ?? 0) + 1;
        }

        return $candidate;
    }

    private function replaceTemplateScopedTables(): void
    {
        foreach (self::TEMPLATE_SCOPED_TABLES as $table) {
            if ($this->skipPrices && $table === 'event_template_price_per_person') {
                continue;
            }

            $deleted = DB::table($table)->whereIn('event_template_id', $this->prodTemplateIds)->delete();
            $this->stats[$table.'_deleted'] = $deleted;

            $cols = $this->commonColumns($table);
            $sql = 'SELECT * FROM '.$table.' WHERE event_template_id IN ('.$this->idListSql($this->prodTemplateIds).')';

            $guard = null;
            if (in_array('start_place_id', $cols, true)) {
                $guard = function (array $row): bool {
                    $placeId = $row['start_place_id'] ?? null;
                    if ($placeId === null || $placeId === '') {
                        return true;
                    }

                    return isset($this->localPlaceIds[(int) $placeId]);
                };
            }

            if (in_array('end_place_id', $cols, true)) {
                $startGuard = $guard;
                $guard = function (array $row) use ($startGuard): bool {
                    if ($startGuard && ! $startGuard($row)) {
                        return false;
                    }
                    $endId = $row['end_place_id'] ?? null;
                    if ($endId === null || $endId === '') {
                        return true;
                    }

                    return isset($this->localPlaceIds[(int) $endId]);
                };
            }

            $inserted = $this->streamInsert($table, $sql, $cols, $guard);
            $this->stats[$table.'_inserted'] = $inserted;
        }
    }

    private function syncHotelRoomPrices(): void
    {
        $updated = 0;
        foreach ($this->sqlite->query('SELECT id, price, updated_at FROM hotel_rooms') as $row) {
            $id = (int) $row['id'];
            $local = DB::table('hotel_rooms')->where('id', $id)->first(['price']);
            if (! $local) {
                continue;
            }
            if (abs((float) $local->price - (float) $row['price']) <= 0.01) {
                continue;
            }
            DB::table('hotel_rooms')->where('id', $id)->update([
                'price' => $row['price'],
                'updated_at' => $row['updated_at'] ?? now(),
            ]);
            $updated++;
        }
        $this->stats['hotel_rooms_price_update'] = $updated;
    }

    /**
     * @param  list<string>  $cols
     * @param  (callable(array): bool)|null  $guard
     */
    private function streamInsert(string $table, string $sqliteSql, array $cols, ?callable $guard = null): int
    {
        // Nie kopiujemy PK z produ — unika kolizji z lokalnymi wierszami szablonów testowych.
        $cols = array_values(array_filter($cols, fn (string $col): bool => $col !== 'id'));

        $batch = [];
        $inserted = 0;
        $skipped = 0;
        $deduped = 0;
        $seen = [];
        $dedupeCols = self::DEDUPE_KEYS[$table] ?? null;

        foreach ($this->sqlite->query($sqliteSql) as $row) {
            if ($guard && ! $guard($row)) {
                $skipped++;

                continue;
            }

            if ($dedupeCols !== null) {
                $keyParts = [];
                foreach ($dedupeCols as $col) {
                    $keyParts[] = (string) ($row[$col] ?? '');
                }
                $key = implode("\0", $keyParts);
                if (isset($seen[$key])) {
                    $deduped++;

                    continue;
                }
                $seen[$key] = true;
            }

            $batch[] = $this->pickRow($row, $cols, $table);
            if (count($batch) >= 250) {
                DB::table($table)->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table($table)->insert($batch);
            $inserted += count($batch);
        }

        if ($skipped > 0) {
            $this->warnings[] = "{$table}: pominięto {$skipped} wierszy (brak FK place).";
            $this->stats[$table.'_skipped'] = $skipped;
        }
        if ($deduped > 0) {
            $this->warnings[] = "{$table}: pominięto {$deduped} duplikatów unique key z produ.";
            $this->stats[$table.'_deduped'] = $deduped;
        }

        return $inserted;
    }

    /**
     * @return list<string>
     */
    private function commonColumns(string $table): array
    {
        if (isset($this->commonColumns[$table])) {
            return $this->commonColumns[$table];
        }

        $sqliteCols = array_column(
            $this->sqlite->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC),
            'name'
        );
        $mysqlCols = array_map(
            fn ($col) => $col->Field,
            DB::select("DESCRIBE `{$table}`")
        );

        $common = array_values(array_intersect($sqliteCols, $mysqlCols));
        if ($common === []) {
            throw new RuntimeException("Brak wspólnych kolumn dla tabeli {$table}");
        }

        return $this->commonColumns[$table] = $common;
    }

    /**
     * Domyślne wartości gdy prod ma NULL, a MySQL wymaga NOT NULL.
     *
     * @var array<string, array<string, mixed>>
     */
    private const NULL_DEFAULTS = [
        'tags' => [
            'status' => 'active',
            'visibility' => 'internal',
        ],
        'event_template_program_points' => [
            'order' => 0,
        ],
        'event_template_program_point_parent' => [
            'order' => 0,
        ],
    ];

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $cols
     * @return array<string, mixed>
     */
    private function pickRow(array $row, array $cols, ?string $table = null): array
    {
        $out = [];
        foreach ($cols as $col) {
            $value = $row[$col] ?? null;
            if (is_string($value) && $value === '' && $this->shouldNullEmpty($col)) {
                $value = null;
            }
            if ($value === null && $table !== null && array_key_exists($col, self::NULL_DEFAULTS[$table] ?? [])) {
                $value = self::NULL_DEFAULTS[$table][$col];
            }
            $out[$col] = $value;
        }

        return $out;
    }

    private function shouldNullEmpty(string $col): bool
    {
        return str_ends_with($col, '_id')
            || in_array($col, [
                'transfer_km', 'program_km', 'transfer_km2', 'program_km2',
                'price_per_person', 'price_base', 'markup_amount', 'tax_amount',
                'price_with_tax', 'transport_cost', 'unit_price', 'deleted_at',
            ], true);
    }

    /**
     * @param  list<int>  $ids
     */
    private function idListSql(array $ids): string
    {
        if ($ids === []) {
            return '0';
        }

        return implode(',', array_map('intval', $ids));
    }
}
