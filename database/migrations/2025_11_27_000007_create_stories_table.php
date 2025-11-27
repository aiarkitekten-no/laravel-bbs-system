<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('title_en');
            $table->string('title_no');
            $table->text('content_en');
            $table->text('content_no');
            $table->string('ai_model')->nullable(); // which AI generated it
            $table->text('ai_prompt')->nullable(); // the prompt used
            $table->integer('upvotes')->default(0);
            $table->integer('downvotes')->default(0);
            $table->integer('view_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->date('story_date'); // the date this story is for
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->unique(['category_id', 'story_date']); // one story per category per day
            $table->index('story_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
