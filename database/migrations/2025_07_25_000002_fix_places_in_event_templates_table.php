<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            // Usuń istniejące kolumny z foreign key
            $table->dropForeign(['start_place_id']);
            $table->dropForeign(['end_place_id']);
            $table->dropColumn(['start_place_id', 'end_place_id']);
        });

        Schema::table('event_templates', function (Blueprint $table) {
            // Dodaj kolumny ponownie bez foreign key constraints
            $table->unsignedBigInteger('start_place_id')->nullable()->after('bus_id');
            $table->unsignedBigInteger('end_place_id')->nullable()->after('start_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropColumn(['start_place_id', 'end_place_id']);
        });
    }
};
