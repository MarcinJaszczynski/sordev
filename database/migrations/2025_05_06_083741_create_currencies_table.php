<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrenciesTable extends Migration
{
    public function up()
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nazwa waluty
            $table->string('symbol'); // Symbol waluty
            $table->string('code')->nullable(); // Kod waluty (np. PLN) – dodane pod testy i wyszukiwanie
            $table->decimal('exchange_rate', 10, 4)->default(1); // Domyślny kurs wymiany
            $table->timestamp('last_updated_at')->nullable(); // Data ostatniej aktualizacji kursu
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('currencies');
    }
}
