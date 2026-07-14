<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_program_points') && ! Schema::hasColumn('event_program_points', 'unit')) {
            Schema::table('event_program_points', function (Blueprint $table) {
                if (Schema::hasColumn('event_program_points', 'quantity')) {
                    $table->string('unit', 32)->nullable()->after('quantity');
                } else {
                    $table->string('unit', 32)->nullable();
                }
            });
        }

        if (Schema::hasTable('event_template_program_points') && ! Schema::hasColumn('event_template_program_points', 'unit')) {
            Schema::table('event_template_program_points', function (Blueprint $table) {
                foreach (['group_size', 'unit_price', 'name', 'order'] as $anchor) {
                    if (Schema::hasColumn('event_template_program_points', $anchor)) {
                        $table->string('unit', 32)->nullable()->after($anchor);

                        return;
                    }
                }

                $table->string('unit', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['event_program_points', 'event_template_program_points'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'unit')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }
    }
};
