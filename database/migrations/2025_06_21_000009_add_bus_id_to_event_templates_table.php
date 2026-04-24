<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->foreignId('bus_id')->nullable()->constrained('buses')->nullOnDelete()->after('program_km');
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropForeign(['bus_id']);
            $table->dropColumn('bus_id');
        });
    }
};
