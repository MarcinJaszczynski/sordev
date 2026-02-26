<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('blog_posts', 'excerpt')) {
                $table->text('excerpt')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('blog_posts', 'featured_image')) {
                $table->string('featured_image')->nullable()->after('excerpt');
            }
            if (!Schema::hasColumn('blog_posts', 'seo_meta')) {
                $table->json('seo_meta')->nullable()->after('featured_image');
            }
            if (!Schema::hasColumn('blog_posts', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('status');
            }
            if (!Schema::hasColumn('blog_posts', 'gallery')) {
                $table->json('gallery')->nullable()->after('seo_meta');
            }
        });
    }

    public function down()
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            if (Schema::hasColumn('blog_posts', 'gallery')) {
                $table->dropColumn('gallery');
            }
            if (Schema::hasColumn('blog_posts', 'is_published')) {
                $table->dropColumn('is_published');
            }
            if (Schema::hasColumn('blog_posts', 'seo_meta')) {
                $table->dropColumn('seo_meta');
            }
            if (Schema::hasColumn('blog_posts', 'featured_image')) {
                $table->dropColumn('featured_image');
            }
            if (Schema::hasColumn('blog_posts', 'excerpt')) {
                $table->dropColumn('excerpt');
            }
        });
    }
};
