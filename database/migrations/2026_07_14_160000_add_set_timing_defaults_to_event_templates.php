<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_templates')) {
            return;
        }

        Schema::table('event_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_templates', 'set_default_child_count')) {
                $table->unsignedTinyInteger('set_default_child_count')->nullable();
            }

            if (! Schema::hasColumn('event_templates', 'set_default_slot_minutes')) {
                $table->unsignedSmallInteger('set_default_slot_minutes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_templates')) {
            return;
        }

        Schema::table('event_templates', function (Blueprint $table): void {
            foreach (['set_default_child_count', 'set_default_slot_minutes'] as $column) {
                if (Schema::hasColumn('event_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
