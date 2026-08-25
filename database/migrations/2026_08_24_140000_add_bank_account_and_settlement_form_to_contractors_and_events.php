<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'bank_account')) {
                $table->string('bank_account', 64)->nullable()->after('nip');
            }

            if (! Schema::hasColumn('contractors', 'settlement_form')) {
                $table->string('settlement_form', 32)->nullable()->after('bank_account');
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'pilot_settlement_form')) {
                $table->string('pilot_settlement_form', 32)->nullable()->after('pilot_contractor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'pilot_settlement_form')) {
                $table->dropColumn('pilot_settlement_form');
            }
        });

        Schema::table('contractors', function (Blueprint $table): void {
            $drop = [];
            if (Schema::hasColumn('contractors', 'settlement_form')) {
                $drop[] = 'settlement_form';
            }
            if (Schema::hasColumn('contractors', 'bank_account')) {
                $drop[] = 'bank_account';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
