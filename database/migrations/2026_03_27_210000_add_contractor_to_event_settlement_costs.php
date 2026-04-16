<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('contractor_id')->nullable()->after('settlement_id');
            $table->foreign('contractor_id')->references('id')->on('contractors')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('event_settlement_costs', function (Blueprint $table) {
            $table->dropForeign(['contractor_id']);
            $table->dropColumn('contractor_id');
        });
    }
};
