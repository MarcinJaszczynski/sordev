<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('reservations', 'confirm_by')) {
                $table->date('confirm_by')->nullable()->after('expires_at');
            }

            if (! Schema::hasColumn('reservations', 'confirmed_at')) {
                $table->date('confirmed_at')->nullable()->after('confirm_by');
            }

            if (! Schema::hasColumn('reservations', 'deposit_due_at')) {
                $table->date('deposit_due_at')->nullable()->after('confirmed_at');
            }

            if (! Schema::hasColumn('reservations', 'deposit_paid_at')) {
                $table->date('deposit_paid_at')->nullable()->after('deposit_due_at');
            }

            if (! Schema::hasColumn('reservations', 'office_notes')) {
                $table->text('office_notes')->nullable()->after('notes');
            }
        });

        if (Schema::hasColumn('reservations', 'confirm_by') && Schema::hasColumn('reservations', 'expires_at')) {
            DB::table('reservations')
                ->whereNull('confirm_by')
                ->whereNotNull('expires_at')
                ->update(['confirm_by' => DB::raw('DATE(expires_at)')]);
        }

        if (Schema::hasColumn('reservations', 'deposit_due_at')) {
            DB::table('reservations')
                ->whereNull('deposit_due_at')
                ->where(function ($query): void {
                    $query->whereNotNull('expires_at')
                        ->orWhereNotNull('reserved_at');
                })
                ->update([
                    'deposit_due_at' => DB::raw('COALESCE(DATE(expires_at), DATE(reserved_at))'),
                ]);
        }

        if (Schema::hasColumn('reservations', 'confirmed_at')) {
            DB::table('reservations')
                ->whereNull('confirmed_at')
                ->whereIn('status', ['confirmed', 'partially_confirmed', 'completed'])
                ->whereNotNull('reserved_at')
                ->update(['confirmed_at' => DB::raw('DATE(reserved_at)')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table): void {
            foreach (['confirm_by', 'confirmed_at', 'deposit_due_at', 'deposit_paid_at', 'office_notes'] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
