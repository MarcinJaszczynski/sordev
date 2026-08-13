<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'pilot_contractor_id')) {
                $table->foreignId('pilot_contractor_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('contractors')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'pilot_contractor_id')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pilot_contractor_id');
            });
        }
    }
};
