<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->foreignId('markup_id')->nullable()->after('bus_id')->constrained('markups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropForeign(['markup_id']);
            $table->dropColumn('markup_id');
        });
    }
};
