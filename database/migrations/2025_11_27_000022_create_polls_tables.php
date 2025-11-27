<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('author_id');
            $table->string('question');
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_multiple')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->integer('total_votes')->default(0);
            $table->timestamps();
            
            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
        });
        
        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->string('option_text');
            $table->integer('vote_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->foreign('poll_id')->references('id')->on('polls')->cascadeOnDelete();
        });
        
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('option_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            
            $table->foreign('poll_id')->references('id')->on('polls')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('poll_options')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['poll_id', 'user_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
