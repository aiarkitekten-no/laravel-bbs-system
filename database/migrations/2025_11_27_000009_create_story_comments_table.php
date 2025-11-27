<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('story_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parent_id')->nullable(); // for nested comments
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('story_id')->references('id')->on('stories')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('story_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_comments');
    }
};
