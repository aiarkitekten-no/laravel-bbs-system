<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('score');
            $table->integer('level_reached')->nullable();
            $table->integer('time_played')->nullable(); // seconds
            $table->json('game_data')->nullable(); // game-specific data
            $table->timestamps();
            
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['game_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
    }
};
