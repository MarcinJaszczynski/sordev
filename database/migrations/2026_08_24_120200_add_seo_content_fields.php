<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_templates') && ! Schema::hasColumn('event_templates', 'featured_image_alt')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->string('featured_image_alt')->nullable()->after('featured_image');
            });
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('blog_posts', 'content_type')) {
                    $table->string('content_type', 30)->default('aktualnosci')->after('slug');
                }
                if (! Schema::hasColumn('blog_posts', 'guide_category')) {
                    $table->string('guide_category')->nullable()->after('content_type');
                }
                if (! Schema::hasColumn('blog_posts', 'featured_image_alt')) {
                    $table->string('featured_image_alt')->nullable()->after('featured_image');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_templates') && Schema::hasColumn('event_templates', 'featured_image_alt')) {
            Schema::table('event_templates', function (Blueprint $table) {
                $table->dropColumn('featured_image_alt');
            });
        }

        if (Schema::hasTable('blog_posts')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                foreach (['content_type', 'guide_category', 'featured_image_alt'] as $column) {
                    if (Schema::hasColumn('blog_posts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
