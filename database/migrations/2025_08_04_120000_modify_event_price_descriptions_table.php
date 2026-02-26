<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_price_descriptions', function (Blueprint $table) {
            // Usuń event_id jeśli istnieje
            if (Schema::hasColumn('event_price_descriptions', 'event_id')) {
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            }
            // Dodaj name jeśli nie istnieje
            if (!Schema::hasColumn('event_price_descriptions', 'name')) {
                $table->string('name')->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_price_descriptions', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->unsignedBigInteger('event_id')->nullable();
            // Przywrócenie klucza obcego jeśli potrzeba
            // $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });
    }
};
