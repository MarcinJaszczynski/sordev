<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_template_day_insurance', function (Blueprint $table) {
            // Keep a dedicated index for FK support before replacing the composite unique index.
            $table->index('event_template_id', 'etdi_event_template_id_idx');
            $table->dropUnique(['event_template_id', 'day']);
            $table->unique(['event_template_id', 'day', 'insurance_id'], 'etdi_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_template_day_insurance', function (Blueprint $table) {
            $table->dropUnique('etdi_unique');
            $table->unique(['event_template_id', 'day']);
            $table->dropIndex('etdi_event_template_id_idx');
        });
    }
};
