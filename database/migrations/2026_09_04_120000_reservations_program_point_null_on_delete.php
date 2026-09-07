<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bezpieczna zmiana FK reservations.program_point_id → ON DELETE SET NULL.
 * Nie woła dropForeign(['program_point_id']) — na części baz ten constraint
 * nie istnieje pod nazwą Laravelową i wtedy migrate pada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'program_point_id')) {
            return;
        }

        $keys = $this->programPointForeignKeys();

        foreach ($keys as $fk) {
            if ($this->isSetNull((string) ($fk['on_delete'] ?? ''))) {
                return;
            }

            $this->dropForeignIfExists((string) $fk['name']);
        }

        $this->nullOrphanedProgramPointIds();

        if ($this->programPointForeignKeys() !== []) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('program_point_id')
                ->references('id')
                ->on('event_program_points')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations') || ! Schema::hasColumn('reservations', 'program_point_id')) {
            return;
        }

        foreach ($this->programPointForeignKeys() as $fk) {
            $this->dropForeignIfExists((string) $fk['name']);
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('program_point_id')
                ->references('id')
                ->on('event_program_points')
                ->cascadeOnDelete();
        });
    }

    /**
     * @return list<array{name: string, on_delete: string}>
     */
    private function programPointForeignKeys(): array
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return collect(Schema::getForeignKeys('reservations'))
                ->filter(fn (array $fk): bool => in_array('program_point_id', $fk['columns'] ?? [], true))
                ->map(fn (array $fk): array => [
                    'name' => (string) ($fk['name'] ?? ''),
                    'on_delete' => (string) ($fk['on_delete'] ?? ''),
                ])
                ->filter(fn (array $fk): bool => $fk['name'] !== '')
                ->values()
                ->all();
        }

        $rows = DB::select(
            'SELECT kcu.CONSTRAINT_NAME AS name, rc.DELETE_RULE AS on_delete
             FROM information_schema.KEY_COLUMN_USAGE kcu
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
               AND rc.TABLE_NAME = kcu.TABLE_NAME
             WHERE kcu.TABLE_SCHEMA = ?
               AND kcu.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL',
            [$connection->getDatabaseName(), 'reservations', 'program_point_id'],
        );

        return collect($rows)
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'on_delete' => (string) $row->on_delete,
            ])
            ->filter(fn (array $fk): bool => $fk['name'] !== '')
            ->values()
            ->all();
    }

    private function dropForeignIfExists(string $name): void
    {
        if ($name === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `reservations` DROP FOREIGN KEY `{$name}`");
        } catch (QueryException) {
            // Constraint o tej nazwie nie istnieje na tej bazie — to jest OK.
        }
    }

    private function isSetNull(string $onDelete): bool
    {
        return strtolower(str_replace([' ', '_'], '', $onDelete)) === 'setnull';
    }

    private function nullOrphanedProgramPointIds(): void
    {
        if (! Schema::hasTable('event_program_points')) {
            return;
        }

        DB::table('reservations')
            ->whereNotNull('program_point_id')
            ->whereNotIn('program_point_id', DB::table('event_program_points')->select('id'))
            ->update(['program_point_id' => null]);
    }
};
