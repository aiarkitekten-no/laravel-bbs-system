<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Player state for complex door games (LORD, Trade Wars, etc)
        Schema::create('game_player_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->unsignedBigInteger('user_id');
            $table->json('state'); // all game state data
            $table->integer('turns_today')->default(0);
            $table->integer('turns_total')->default(0);
            $table->date('last_played_date')->nullable();
            $table->timestamps();
            
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['game_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_states');
    }
};
