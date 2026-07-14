<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_program_points', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_program_points', 'is_transport')) {
                $table->boolean('is_transport')->default(false)->after('is_hotel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_program_points', function (Blueprint $table): void {
            if (Schema::hasColumn('event_program_points', 'is_transport')) {
                $table->dropColumn('is_transport');
            }
        });
    }
};
