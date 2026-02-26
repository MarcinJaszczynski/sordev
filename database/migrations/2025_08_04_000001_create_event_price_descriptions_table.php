<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('event_price_descriptions')) {
            Schema::create('event_price_descriptions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description'); // HTML-formatted text
                $table->timestamps();
                // Usunięto klucz obcy event_id, pole name jest tekstowe
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_price_descriptions');
    }
};
