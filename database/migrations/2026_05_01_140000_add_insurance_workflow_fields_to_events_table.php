<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'insurance_policy_number')) {
                $table->string('insurance_policy_number')->nullable()->after('pickup_place_details');
            }

            if (! Schema::hasColumn('events', 'insurance_terms')) {
                $table->text('insurance_terms')->nullable()->after('insurance_policy_number');
            }

            if (! Schema::hasColumn('events', 'insurance_document_path')) {
                $table->string('insurance_document_path')->nullable()->after('insurance_terms');
            }

            if (! Schema::hasColumn('events', 'insurance_amount')) {
                $table->decimal('insurance_amount', 12, 2)->nullable()->after('insurance_document_path');
            }

            if (! Schema::hasColumn('events', 'insurance_payment_status')) {
                $table->string('insurance_payment_status')->default('pending')->after('insurance_amount');
            }

            if (! Schema::hasColumn('events', 'insurance_status')) {
                $table->string('insurance_status')->default('pending')->after('insurance_payment_status');
            }

            if (! Schema::hasColumn('events', 'insurance_paid_at')) {
                $table->dateTime('insurance_paid_at')->nullable()->after('insurance_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table): void {
            $drop = [];

            foreach ([
                'insurance_policy_number',
                'insurance_terms',
                'insurance_document_path',
                'insurance_amount',
                'insurance_payment_status',
                'insurance_status',
                'insurance_paid_at',
            ] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $drop[] = $column;
                }
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
