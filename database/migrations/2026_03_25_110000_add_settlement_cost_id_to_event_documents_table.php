<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_documents', function (Blueprint $table) {
            $table->foreignId('settlement_cost_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_settlement_costs')
                ->nullOnDelete()
                ->index();
        });
    }

    public function down(): void
    {
        // Column and FK already dropped manually; nothing to do here.
    }
};
