<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payment_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bank', 50)->default('millennium');
            $table->string('source_filename')->nullable();
            $table->unsignedInteger('lines_total')->default(0);
            $table->unsignedInteger('lines_applied')->default(0);
            $table->unsignedInteger('lines_skipped')->default(0);
            $table->string('status', 30)->default('preview');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_payment_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('bank_payment_import_batches')->cascadeOnDelete();
            $table->date('operation_date')->nullable();
            $table->string('title')->nullable();
            $table->string('counterparty')->nullable();
            $table->string('account_number', 64)->nullable();
            $table->decimal('amount_pln', 12, 2);
            $table->string('fingerprint', 64)->index();
            $table->string('match_status', 30)->default('unmatched');
            $table->string('match_reason')->nullable();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('participant_payment_id')->nullable()->constrained('event_settlement_participant_payments')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->boolean('selected')->default(true);
            $table->boolean('applied')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->text('apply_notes')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payment_import_lines');
        Schema::dropIfExists('bank_payment_import_batches');
    }
};
