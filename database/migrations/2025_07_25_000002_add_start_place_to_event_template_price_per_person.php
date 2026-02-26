<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Kolumny już istnieją, dodajemy tylko indeks
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            // Sprawdź czy kolumny istnieją, jeśli nie - dodaj je
            if (!Schema::hasColumn('event_template_price_per_person', 'start_place_id')) {
                $table->unsignedBigInteger('start_place_id')->nullable()->after('currency_id');
            }
            if (!Schema::hasColumn('event_template_price_per_person', 'transport_cost')) {
                $table->decimal('transport_cost', 10, 2)->nullable()->after('price_per_person')->comment('Koszt transportu w PLN');
            }
            
            // Dodaj unikalny indeks jeśli nie istnieje
            try {
                $table->unique(['event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id'], 'unique_price_per_place');
            } catch (Exception $e) {
                // Indeks już istnieje
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            $table->dropUnique('unique_price_per_place');
            $table->dropColumn(['start_place_id', 'transport_cost']);
        });
    }
};
