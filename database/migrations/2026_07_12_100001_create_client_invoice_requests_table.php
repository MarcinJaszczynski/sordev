<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_invoice_requests')) {
            return;
        }

        Schema::create('client_invoice_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('nip', 16);
            $table->string('street')->nullable();
            $table->string('house_number', 32)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city')->nullable();
            $table->string('invoice_email');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        if (Schema::hasTable('contracts')) {
            Schema::table('client_invoice_requests', function (Blueprint $table) {
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoice_requests');
    }
};
