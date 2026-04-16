<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settlement_documents', function (Blueprint $table) {
            $table->boolean('attach_to_pilot_pdf')->default(false)->after('notes');
            $table->boolean('attach_to_hotel_pdf')->default(false)->after('attach_to_pilot_pdf');
            $table->boolean('attach_to_driver_pdf')->default(false)->after('attach_to_hotel_pdf');
            $table->boolean('attach_to_folder_pdf')->default(false)->after('attach_to_driver_pdf');

            $table->index(['settlement_id', 'attach_to_pilot_pdf']);
            $table->index(['settlement_id', 'attach_to_hotel_pdf']);
            $table->index(['settlement_id', 'attach_to_driver_pdf']);
            $table->index(['settlement_id', 'attach_to_folder_pdf']);
        });
    }

    public function down(): void
    {
        Schema::table('event_settlement_documents', function (Blueprint $table) {
            $table->dropIndex(['settlement_id', 'attach_to_pilot_pdf']);
            $table->dropIndex(['settlement_id', 'attach_to_hotel_pdf']);
            $table->dropIndex(['settlement_id', 'attach_to_driver_pdf']);
            $table->dropIndex(['settlement_id', 'attach_to_folder_pdf']);

            $table->dropColumn([
                'attach_to_pilot_pdf',
                'attach_to_hotel_pdf',
                'attach_to_driver_pdf',
                'attach_to_folder_pdf',
            ]);
        });
    }
};
