<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table) {
            $table->id('post_id');
            $table->foreignId('post_category_id')
                ->constrained('post_categories', 'post_category_id')
                ->restrictOnDelete();

            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();

            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('content');
            $table->string('thumbnail_url')->nullable();
            $table->string('banner_url')->nullable();

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('seo_keywords')->nullable();

            $table->enum('post_type', ['news', 'promotion', 'guide', 'review'])->default('news');
            $table->enum('status', ['draft', 'published', 'hidden'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->dateTime('published_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};