<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            // Unikalna kombinacja: szablon + wariant ilości + waluta + miejsce startowe
            $table->unique([
                'event_template_id',
                'event_template_qty_id',
                'currency_id',
                'start_place_id',
            ], 'uniq_event_tpl_price_combo');
        });
    }

    public function down(): void
    {
        Schema::table('event_template_price_per_person', function (Blueprint $table) {
            $table->dropUnique('uniq_event_tpl_price_combo');
        });
    }
};
