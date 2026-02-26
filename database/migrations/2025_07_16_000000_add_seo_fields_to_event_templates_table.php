<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 350)->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_canonical')->nullable();
            $table->string('seo_og_title')->nullable();
            $table->string('seo_og_description', 350)->nullable();
            $table->string('seo_og_image')->nullable();
            $table->string('seo_twitter_title')->nullable();
            $table->string('seo_twitter_description', 350)->nullable();
            $table->string('seo_twitter_image')->nullable();
            $table->json('seo_schema')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_templates', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_keywords',
                'seo_canonical',
                'seo_og_title',
                'seo_og_description',
                'seo_og_image',
                'seo_twitter_title',
                'seo_twitter_description',
                'seo_twitter_image',
                'seo_schema',
            ]);
        });
    }
};
