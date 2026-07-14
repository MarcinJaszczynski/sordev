<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_contractor') && ! Schema::hasTable('contractor_contact')) {
            Schema::rename('contact_contractor', 'contractor_contact');
        }

        if (! Schema::hasTable('contractor_contact')) {
            Schema::create('contractor_contact', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['contractor_id', 'contact_id'], 'contractor_contact_unique');
            });

            return;
        }

        if (Schema::hasTable('contact_contractor')) {
            $existingPairs = DB::table('contractor_contact')
                ->select('contractor_id', 'contact_id')
                ->get()
                ->map(fn ($row) => $row->contractor_id.'-'.$row->contact_id)
                ->all();

            DB::table('contact_contractor')
                ->orderBy('id')
                ->get()
                ->each(function ($row) use ($existingPairs): void {
                    $key = $row->contractor_id.'-'.$row->contact_id;

                    if (in_array($key, $existingPairs, true)) {
                        return;
                    }

                    DB::table('contractor_contact')->insertOrIgnore([
                        'contractor_id' => $row->contractor_id,
                        'contact_id' => $row->contact_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });

            Schema::drop('contact_contractor');
        }

        $this->ensureUniqueIndex();
    }

    public function down(): void
    {
        if (Schema::hasTable('contractor_contact') && ! Schema::hasTable('contact_contractor')) {
            Schema::rename('contractor_contact', 'contact_contractor');
        }
    }

    private function ensureUniqueIndex(): void
    {
        $connection = Schema::getConnection();
        $table = 'contractor_contact';

        if (! Schema::hasTable($table)) {
            return;
        }

        $indexes = collect($connection->getSchemaBuilder()->getIndexes($table))
            ->pluck('name')
            ->all();

        if (! in_array('contractor_contact_unique', $indexes, true)) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(['contractor_id', 'contact_id'], 'contractor_contact_unique');
            });
        }
    }
};
