<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->foreignId('start_place_id')->nullable()->after('bus_id')->constrained('places')->nullOnDelete();
            $table->foreignId('end_place_id')->nullable()->after('start_place_id')->constrained('places')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropForeign(['start_place_id']);
            $table->dropForeign(['end_place_id']);
            $table->dropColumn(['start_place_id', 'end_place_id']);
        });
    }
};
