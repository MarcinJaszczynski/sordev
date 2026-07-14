<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pilot_currency_exchanges')) {
            return;
        }

        Schema::create('pilot_currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->decimal('from_amount', 12, 2);
            $table->decimal('to_amount', 12, 2);
            $table->decimal('exchange_rate', 12, 5);
            $table->decimal('rate_difference_pln', 12, 2)->default(0);
            $table->timestamp('exchanged_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_currency_exchanges');
    }
};
