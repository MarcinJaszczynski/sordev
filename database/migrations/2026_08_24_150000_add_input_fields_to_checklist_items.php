<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->string('input_type', 32)->default('check_only')->after('description');
            $table->string('input_label')->nullable()->after('input_type');
            $table->boolean('input_required')->default(false)->after('input_label');
            $table->string('input_unit', 32)->nullable()->after('input_required');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('checklist_input_type', 32)->nullable()->after('source');
            $table->string('checklist_input_label')->nullable()->after('checklist_input_type');
            $table->boolean('checklist_input_required')->default(false)->after('checklist_input_label');
            $table->string('checklist_input_unit', 32)->nullable()->after('checklist_input_required');
            $table->text('checklist_response')->nullable()->after('checklist_input_unit');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_template_items', function (Blueprint $table) {
            $table->dropColumn(['input_type', 'input_label', 'input_required', 'input_unit']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'checklist_input_type',
                'checklist_input_label',
                'checklist_input_required',
                'checklist_input_unit',
                'checklist_response',
            ]);
        });
    }
};
