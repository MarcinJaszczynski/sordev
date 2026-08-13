<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            return;
        }

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('client_invoice_requests', 'buyer_type')) {
                $table->string('buyer_type', 32)->default('company')->after('user_id');
            }
            if (! Schema::hasColumn('client_invoice_requests', 'source')) {
                $table->string('source', 32)->default('portal')->after('buyer_type');
            }
            if (! Schema::hasColumn('client_invoice_requests', 'applicant_phone')) {
                $table->string('applicant_phone', 50)->nullable()->after('invoice_email');
            }
            if (! Schema::hasColumn('client_invoice_requests', 'event_code_entered')) {
                $table->string('event_code_entered', 32)->nullable()->after('event_id');
            }
        });

        // Publiczny wniosek: bez konta / bez powiązania z imprezą (biuro łączy później).
        Schema::table('client_invoice_requests', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('nip', 16)->nullable()->change();
        });

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_invoice_requests')) {
            return;
        }

        Schema::table('client_invoice_requests', function (Blueprint $table) {
            if (Schema::hasColumn('client_invoice_requests', 'event_code_entered')) {
                $table->dropColumn('event_code_entered');
            }
            if (Schema::hasColumn('client_invoice_requests', 'applicant_phone')) {
                $table->dropColumn('applicant_phone');
            }
            if (Schema::hasColumn('client_invoice_requests', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('client_invoice_requests', 'buyer_type')) {
                $table->dropColumn('buyer_type');
            }
        });
    }
};
