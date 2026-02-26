<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('event_template_event_price_description')) {
            Schema::create('event_template_event_price_description', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_template_id')->constrained()->onDelete('cascade');
                $table->foreignId('event_price_description_id')->nullable()->constrained()->onDelete('set null');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_template_event_price_description');
    }
};
