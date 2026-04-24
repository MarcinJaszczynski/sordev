<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_post_id');
            $table->unsignedBigInteger('tag_id');

            $table->foreign('blog_post_id')->references('id')->on('blog_posts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

            $table->primary(['blog_post_id', 'tag_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('blog_post_tag');
    }
};
