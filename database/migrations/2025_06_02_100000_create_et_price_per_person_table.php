<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_template_price_per_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_template_qty_id')->constrained('event_template_qties')->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->decimal('price_per_person', 12, 2);
            $table->timestamps();
            $table->unique(['event_template_id', 'event_template_qty_id', 'currency_id'], 'etpp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_price_per_person');
    }
};
