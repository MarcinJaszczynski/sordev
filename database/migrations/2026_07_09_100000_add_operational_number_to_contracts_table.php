<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('contracts', 'operational_number')) {
                $table->string('operational_number', 64)->nullable()->after('contract_number');
                $table->index(['event_id', 'operational_number']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            if (Schema::hasColumn('contracts', 'operational_number')) {
                $table->dropIndex(['event_id', 'operational_number']);
                $table->dropColumn('operational_number');
            }
        });
    }
};
