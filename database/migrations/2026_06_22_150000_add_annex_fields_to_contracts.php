<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'annex_change_types')) {
                $table->json('annex_change_types')->nullable()->after('meta');
            }
            if (! Schema::hasColumn('contracts', 'annex_program_change_notes')) {
                $table->text('annex_program_change_notes')->nullable()->after('annex_change_types');
            }
            if (! Schema::hasColumn('contracts', 'annex_program_snapshot')) {
                $table->json('annex_program_snapshot')->nullable()->after('annex_program_change_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $columns = ['annex_program_snapshot', 'annex_program_change_notes', 'annex_change_types'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('contracts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
