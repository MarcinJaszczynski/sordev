<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'transport_contractor_id')) {
                $table->foreignId('transport_contractor_id')
                    ->nullable()
                    ->after('contractor_id')
                    ->constrained('contractors')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('events', 'bus_info')) {
                $table->text('bus_info')->nullable()->after('vehicle_registration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'transport_contractor_id')) {
                $table->dropConstrainedForeignId('transport_contractor_id');
            }

            if (Schema::hasColumn('events', 'bus_info')) {
                $table->dropColumn('bus_info');
            }
        });
    }
};
