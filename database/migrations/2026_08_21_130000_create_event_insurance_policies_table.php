<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_insurance_policies')) {
            Schema::create('event_insurance_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->string('policy_number')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('payment_status', 32)->default('pending');
                $table->decimal('amount', 12, 2)->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->string('document_path')->nullable();
                $table->string('insured_list_path')->nullable();
                $table->text('terms')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
            });
        }

        if (Schema::hasTable('event_day_insurance')
            && ! Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')) {
            Schema::table('event_day_insurance', function (Blueprint $table): void {
                $table->foreignId('event_insurance_policy_id')
                    ->nullable()
                    ->after('insurance_id')
                    ->constrained('event_insurance_policies')
                    ->nullOnDelete();
            });
        }

        $this->migrateLegacyPolicies();
    }

    public function down(): void
    {
        if (Schema::hasTable('event_day_insurance')
            && Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id')) {
            Schema::table('event_day_insurance', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('event_insurance_policy_id');
            });
        }

        Schema::dropIfExists('event_insurance_policies');
    }

    private function migrateLegacyPolicies(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('event_insurance_policies')) {
            return;
        }

        $hasPolicyNumber = Schema::hasColumn('events', 'insurance_policy_number');
        if (! $hasPolicyNumber) {
            return;
        }

        $hasInsuredList = Schema::hasColumn('events', 'insurance_insured_list_path');
        $hasDayPolicyFk = Schema::hasColumn('event_day_insurance', 'event_insurance_policy_id');

        $events = DB::table('events')->orderBy('id')->get([
            'id',
            'insurance_policy_number',
            'insurance_status',
            'insurance_payment_status',
            'insurance_amount',
            'insurance_paid_at',
            'insurance_document_path',
            'insurance_terms',
            ...($hasInsuredList ? ['insurance_insured_list_path'] : []),
        ]);

        foreach ($events as $event) {
            $hasData = filled($event->insurance_policy_number)
                || filled($event->insurance_terms)
                || filled($event->insurance_document_path)
                || filled($event->insurance_amount)
                || filled($event->insurance_paid_at)
                || (filled($event->insurance_status) && $event->insurance_status !== 'pending')
                || (filled($event->insurance_payment_status) && $event->insurance_payment_status !== 'pending')
                || ($hasInsuredList && filled($event->insurance_insured_list_path ?? null));

            $hasDayRows = $hasDayPolicyFk
                && Schema::hasTable('event_day_insurance')
                && DB::table('event_day_insurance')->where('event_id', $event->id)->exists();

            if (! $hasData && ! $hasDayRows) {
                continue;
            }

            $already = DB::table('event_insurance_policies')->where('event_id', $event->id)->exists();
            if ($already) {
                continue;
            }

            if (! $hasData && $hasDayRows) {
                // Same day rows without operational policy — skip creating empty policy.
                continue;
            }

            $policyId = DB::table('event_insurance_policies')->insertGetId([
                'event_id' => $event->id,
                'policy_number' => $event->insurance_policy_number,
                'status' => $event->insurance_status ?: 'pending',
                'payment_status' => $event->insurance_payment_status ?: 'pending',
                'amount' => $event->insurance_amount,
                'paid_at' => $event->insurance_paid_at,
                'document_path' => $event->insurance_document_path,
                'insured_list_path' => $hasInsuredList ? ($event->insurance_insured_list_path ?? null) : null,
                'terms' => $event->insurance_terms,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($hasDayPolicyFk) {
                DB::table('event_day_insurance')
                    ->where('event_id', $event->id)
                    ->whereNull('event_insurance_policy_id')
                    ->update(['event_insurance_policy_id' => $policyId]);
            }
        }
    }
};
