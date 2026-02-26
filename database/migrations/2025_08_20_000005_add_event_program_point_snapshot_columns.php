<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_program_points')) return;

        Schema::table('event_program_points', function (Blueprint $table) {
            if (!Schema::hasColumn('event_program_points', 'name')) {
                $table->string('name')->nullable()->after('event_template_program_point_id');
            }
            if (!Schema::hasColumn('event_program_points', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('event_program_points', 'office_notes')) {
                $table->text('office_notes')->nullable()->after('description');
            }
            if (!Schema::hasColumn('event_program_points', 'pilot_notes')) {
                $table->text('pilot_notes')->nullable()->after('office_notes');
            }
            if (!Schema::hasColumn('event_program_points', 'duration_hours')) {
                $table->integer('duration_hours')->nullable()->after('pilot_notes');
            }
            if (!Schema::hasColumn('event_program_points', 'duration_minutes')) {
                $table->integer('duration_minutes')->nullable()->after('duration_hours');
            }
            if (!Schema::hasColumn('event_program_points', 'featured_image')) {
                $table->string('featured_image')->nullable()->after('duration_minutes');
            }
            if (!Schema::hasColumn('event_program_points', 'gallery_images')) {
                $table->json('gallery_images')->nullable()->after('featured_image');
            }
            if (!Schema::hasColumn('event_program_points', 'group_size')) {
                $table->integer('group_size')->nullable()->after('gallery_images');
            }
            if (!Schema::hasColumn('event_program_points', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('group_size');
                // add index but avoid strict foreign key to prevent migration order issues in old DBs
                $table->index('currency_id');
            }
            if (!Schema::hasColumn('event_program_points', 'convert_to_pln')) {
                $table->boolean('convert_to_pln')->default(false)->after('currency_id');
            }
            if (!Schema::hasColumn('event_program_points', 'show_title_style')) {
                $table->boolean('show_title_style')->default(true)->after('convert_to_pln');
            }
            if (!Schema::hasColumn('event_program_points', 'show_description')) {
                $table->boolean('show_description')->default(true)->after('show_title_style');
            }
            if (!Schema::hasColumn('event_program_points', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('show_description');
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_program_points')) return;

        Schema::table('event_program_points', function (Blueprint $table) {
            $cols = ['parent_id','show_description','show_title_style','convert_to_pln','currency_id','group_size','gallery_images','featured_image','duration_minutes','duration_hours','pilot_notes','office_notes','description','name'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('event_program_points', $col)) {
                    try { $table->dropColumn($col); } catch (\Exception $e) { }
                }
            }
        });
    }
};
