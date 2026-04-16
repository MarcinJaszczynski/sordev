<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_cash_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('event_settlements')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();

            $table->decimal('calculated_amount', 12, 2)->default(0)
                ->comment('Kwota obliczona przez system (suma kosztów pilota w tej walucie)');
            $table->decimal('approved_amount', 12, 2)->nullable()
                ->comment('Kwota zatwierdzona przez biuro do wypłaty');
            $table->decimal('provided_amount', 12, 2)->nullable()
                ->comment('Kwota faktycznie wydana pilotowi przed wyjazdem');
            $table->decimal('spent_amount', 12, 2)->nullable()
                ->comment('Kwota faktycznie wydana przez pilota');
            $table->decimal('returned_amount', 12, 2)->nullable()
                ->comment('Kwota zwrócona przez pilota po wyjeździe');
            $table->decimal('balance', 12, 2)->nullable()
                ->comment('Saldo: provided - spent + returned');

            $table->foreignId('rate_snapshot_id')->nullable()
                ->constrained('currency_rate_snapshots')->nullOnDelete()
                ->comment('Kurs walutowy użyty do przeliczenia');
            $table->decimal('rate_used', 10, 4)->nullable();
            $table->decimal('pln_equivalent', 12, 2)->nullable()
                ->comment('Równowartość w PLN');

            $table->string('status')->default('calculated')
                ->comment('calculated|approved|provided|settled');
            $table->timestamp('provided_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['settlement_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_cash_preparations');
    }
};
