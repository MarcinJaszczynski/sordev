<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 10, 4)->comment('Kurs sprzedaży / domyślny');
            $table->decimal('purchase_rate', 10, 4)->nullable()->comment('Kurs zakupu');
            $table->decimal('sale_rate', 10, 4)->nullable()->comment('Kurs sprzedaży banku');
            $table->string('source')->nullable()->comment('Źródło: NBP, manual, bank, itp.');
            $table->date('rate_date')->comment('Data kursu');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['currency_id', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_snapshots');
    }
};
