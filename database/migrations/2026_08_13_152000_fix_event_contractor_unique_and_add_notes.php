<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        // Poprzednia migracja łapała wyjątek przy dropUnique — indeks często zostawał.
        // Bez tego nie da się zapisać dwóch zamawiających z tą samą firmą (szkoła + rodzic).
        $this->dropEventContractorUniqueIndex();

        Schema::table('event_contractor', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_contractor', 'notes')) {
                $table->text('notes')->nullable()->after('department_label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            return;
        }

        Schema::table('event_contractor', function (Blueprint $table): void {
            if (Schema::hasColumn('event_contractor', 'notes')) {
                $table->dropColumn('notes');
            }
        });

        $sm = Schema::getConnection()->getSchemaBuilder();
        $indexes = collect(method_exists($sm, 'getIndexes') ? $sm->getIndexes('event_contractor') : [])
            ->pluck('name')
            ->all();

        if (! in_array('event_contractor_unique', $indexes, true)) {
            try {
                Schema::table('event_contractor', function (Blueprint $table): void {
                    $table->unique(['event_id', 'contractor_id'], 'event_contractor_unique');
                });
            } catch (\Throwable) {
                // ignore — down best-effort
            }
        }
    }

    private function dropEventContractorUniqueIndex(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                Schema::table('event_contractor', function (Blueprint $table): void {
                    $table->dropUnique('event_contractor_unique');
                });
            } catch (\Throwable) {
                // sqlite test DB może nie mieć indeksu
            }

            return;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'event_contractor')
            ->where('index_name', 'event_contractor_unique')
            ->exists();

        if (! $exists) {
            return;
        }

        // FK event_id korzysta z lewego prefiksu unique (event_id, contractor_id).
        // Najpierw osobny indeks na event_id, dopiero potem drop unique.
        $eventIdIndexExists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'event_contractor')
            ->where('index_name', 'event_contractor_event_id_index')
            ->exists();

        if (! $eventIdIndexExists) {
            Schema::table('event_contractor', function (Blueprint $table): void {
                $table->index('event_id', 'event_contractor_event_id_index');
            });
        }

        DB::statement('ALTER TABLE `event_contractor` DROP INDEX `event_contractor_unique`');
    }
};
