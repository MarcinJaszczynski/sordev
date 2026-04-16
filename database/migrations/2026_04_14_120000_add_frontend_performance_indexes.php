<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_template_starting_place_availability')) {
            Schema::table('event_template_starting_place_availability', function (Blueprint $table) {
                $table->index(
                    ['start_place_id', 'available', 'event_template_id'],
                    'etspa_start_available_template_idx'
                );
            });
        }

        if (Schema::hasTable('event_template_price_per_person')) {
            Schema::table('event_template_price_per_person', function (Blueprint $table) {
                $table->index(
                    ['event_template_id', 'start_place_id', 'price_per_person'],
                    'etpp_template_start_price_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_template_starting_place_availability')) {
            Schema::table('event_template_starting_place_availability', function (Blueprint $table) {
                $table->dropIndex('etspa_start_available_template_idx');
            });
        }

        if (Schema::hasTable('event_template_price_per_person')) {
            Schema::table('event_template_price_per_person', function (Blueprint $table) {
                $table->dropIndex('etpp_template_start_price_idx');
            });
        }
    }
};
