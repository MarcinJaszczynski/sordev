<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TABLE_NAME = 'contract_templates_legacy_fix_20260316';
    private const TARGET_TABLE_NAME = 'contract_templates';

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable(self::TARGET_TABLE_NAME)) {
            return;
        }

        $affectedTables = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND sql LIKE ?",
            ['%' . self::LEGACY_TABLE_NAME . '%']
        );

        if (empty($affectedTables)) {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            foreach ($affectedTables as $table) {
                $tableName = (string) ($table->name ?? '');
                $createSql = (string) ($table->sql ?? '');

                if ($tableName === '' || $createSql === '' || ! str_contains($createSql, self::LEGACY_TABLE_NAME)) {
                    continue;
                }

                $this->rebuildTableWithFixedForeignKey($tableName, $createSql);
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        // No-op: this migration repairs broken SQLite foreign key references.
    }

    private function rebuildTableWithFixedForeignKey(string $tableName, string $createSql): void
    {
        $backupTableName = $tableName . '_fk_fix_backup_20260316';

        Schema::dropIfExists($backupTableName);

        $indexDefinitions = DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL",
            [$tableName]
        );

        $triggerDefinitions = DB::select(
            "SELECT sql FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ? AND sql IS NOT NULL",
            [$tableName]
        );

        DB::statement(sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($backupTableName)
        ));

        $fixedCreateSql = str_replace(self::LEGACY_TABLE_NAME, self::TARGET_TABLE_NAME, $createSql);
        DB::statement($fixedCreateSql);

        $commonColumns = $this->getCommonColumns($backupTableName, $tableName);

        if (! empty($commonColumns)) {
            $columnList = implode(', ', array_map([$this, 'quoteIdentifier'], $commonColumns));

            DB::statement(sprintf(
                'INSERT INTO %s (%s) SELECT %s FROM %s',
                $this->quoteIdentifier($tableName),
                $columnList,
                $columnList,
                $this->quoteIdentifier($backupTableName)
            ));
        }

        Schema::drop($backupTableName);

        foreach ($indexDefinitions as $indexDefinition) {
            $sql = (string) ($indexDefinition->sql ?? '');

            if ($sql !== '') {
                DB::statement($sql);
            }
        }

        foreach ($triggerDefinitions as $triggerDefinition) {
            $sql = (string) ($triggerDefinition->sql ?? '');

            if ($sql !== '') {
                DB::statement($sql);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function getCommonColumns(string $sourceTableName, string $targetTableName): array
    {
        $sourceColumns = DB::select('PRAGMA table_info(' . $this->quoteIdentifier($sourceTableName) . ')');
        $targetColumns = DB::select('PRAGMA table_info(' . $this->quoteIdentifier($targetTableName) . ')');

        $targetColumnNames = [];

        foreach ($targetColumns as $column) {
            $name = (string) ($column->name ?? '');

            if ($name !== '') {
                $targetColumnNames[$name] = true;
            }
        }

        $commonColumns = [];

        foreach ($sourceColumns as $column) {
            $name = (string) ($column->name ?? '');

            if ($name !== '' && isset($targetColumnNames[$name])) {
                $commonColumns[] = $name;
            }
        }

        return $commonColumns;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};
