<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('pilot_funds_paid')->default(false)->after('assigned_to');
            $table->timestamp('pilot_funds_paid_at')->nullable()->after('pilot_funds_paid');
            $table->foreignId('pilot_funds_paid_by')->nullable()->after('pilot_funds_paid_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pilot_funds_paid_by');
            $table->dropColumn(['pilot_funds_paid', 'pilot_funds_paid_at']);
        });
    }
};
