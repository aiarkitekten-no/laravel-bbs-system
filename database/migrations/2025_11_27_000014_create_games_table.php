<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique(); // trivia, hangman, tradewars, lord, etc
            $table->string('name_en');
            $table->string('name_no');
            $table->text('description_en')->nullable();
            $table->text('description_no')->nullable();
            $table->enum('type', ['SIMPLE', 'DOOR', 'DAILY']); // simple=quick games, door=complex, daily=once per day
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // game-specific configuration
            $table->integer('plays_today')->default(0);
            $table->integer('plays_total')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
