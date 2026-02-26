<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            $table->decimal('price_base', 10, 2)->nullable()->after('price_per_person'); // Cena bez podatków
            $table->decimal('markup_amount', 10, 2)->nullable()->after('price_base'); // Kwota narzutu
            $table->decimal('tax_amount', 10, 2)->nullable()->after('markup_amount'); // Łączna kwota podatków
            $table->decimal('price_with_tax', 10, 2)->nullable()->after('tax_amount'); // Cena końcowa z podatkami
            $table->json('tax_breakdown')->nullable()->after('price_with_tax'); // Szczegółowy podział podatków
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            $table->dropColumn([
                'price_base',
                'markup_amount', 
                'tax_amount',
                'price_with_tax',
                'tax_breakdown'
            ]);
        });
    }
};
