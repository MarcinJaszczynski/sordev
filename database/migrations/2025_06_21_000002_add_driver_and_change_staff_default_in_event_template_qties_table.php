<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_template_qties', function (Blueprint $table) {
            $table->integer('driver')->default(1)->after('staff');
            $table->integer('staff')->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_template_qties', function (Blueprint $table) {
            $table->dropColumn('driver');
            $table->integer('staff')->default(2)->change();
        });
    }
};
