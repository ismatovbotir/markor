<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Site news (/news): written by the super admin in uz / ru / zh, body in Markdown. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();               // /news/{slug}
            $table->string('title_uz', 200);
            $table->string('title_ru', 200)->nullable();
            $table->string('title_zh', 200)->nullable();
            $table->string('excerpt_uz', 300)->nullable();
            $table->string('excerpt_ru', 300)->nullable();
            $table->string('excerpt_zh', 300)->nullable();
            $table->text('body_uz')->nullable();
            $table->text('body_ru')->nullable();
            $table->text('body_zh')->nullable();
            $table->timestamp('published_at')->nullable();       // date shown on the site; in the future = not yet visible
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
