<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('text')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('cover')->nullable();
            $table->string('slug');
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedTinyInteger('navbar')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_article_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_article_id');
            $table->unsignedBigInteger('blog_tag_id');
            $table->primary(['blog_article_id', 'blog_tag_id']);
            $table->foreign('blog_article_id')->references('id')->on('blog_articles')->onDelete('cascade');
            $table->foreign('blog_tag_id')->references('id')->on('blog_tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_article_tag');
        Schema::dropIfExists('blog_articles');
        Schema::dropIfExists('blog_tags');
    }
};
