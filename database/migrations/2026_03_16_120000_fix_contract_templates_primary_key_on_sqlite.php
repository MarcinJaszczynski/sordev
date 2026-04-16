<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contract_templates')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $columns = DB::select('PRAGMA table_info(contract_templates)');
        $idColumn = collect($columns)->firstWhere('name', 'id');

        if (! $idColumn) {
            return;
        }

        $idType = strtolower((string) ($idColumn->type ?? ''));
        $hasIntegerPrimaryKey = ((int) ($idColumn->pk ?? 0) === 1) && str_contains($idType, 'int');

        if ($hasIntegerPrimaryKey) {
            return;
        }

        $legacyTable = 'contract_templates_legacy_fix_20260316';

        Schema::dropIfExists($legacyTable);

        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::statement("ALTER TABLE contract_templates RENAME TO {$legacyTable}");

            Schema::create('contract_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('content');
                $table->timestamps();
            });

            $rows = DB::table($legacyTable)
                ->select(['id', 'name', 'content', 'created_at', 'updated_at'])
                ->orderByRaw('rowid asc')
                ->get();

            $usedIds = [];
            $nextId = 1;

            foreach ($rows as $row) {
                $payload = [
                    'name' => $row->name,
                    'content' => $row->content,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];

                $parsedId = filter_var($row->id, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

                if (($parsedId !== false) && ! in_array($parsedId, $usedIds, true)) {
                    $resolvedId = $parsedId;
                } else {
                    while (in_array($nextId, $usedIds, true)) {
                        $nextId++;
                    }

                    $resolvedId = $nextId;
                    $nextId++;
                }

                $payload['id'] = $resolvedId;
                $usedIds[] = $resolvedId;

                DB::table('contract_templates')->insert($payload);
            }

            Schema::drop($legacyTable);
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        // No-op: this migration normalizes broken legacy SQLite schema.
    }
};
