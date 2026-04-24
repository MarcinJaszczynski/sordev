<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_price_per_person', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('event_template_qty_id')->nullable()->index();
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->unsignedBigInteger('start_place_id')->nullable()->index();
            $table->decimal('price_per_person', 12, 2)->default(0);
            $table->decimal('transport_cost', 12, 2)->nullable();
            $table->decimal('price_base', 12, 2)->nullable();
            $table->decimal('markup_amount', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('price_with_tax', 12, 2)->nullable();
            $table->json('tax_breakdown')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_price_per_person');
    }
};
