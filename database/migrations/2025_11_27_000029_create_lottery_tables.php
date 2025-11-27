<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_lottery', function (Blueprint $table) {
            $table->id();
            $table->date('draw_date')->unique();
            $table->json('winning_numbers'); // array of numbers
            $table->integer('jackpot')->default(1000);
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->integer('winner_prize')->nullable();
            $table->boolean('is_drawn')->default(false);
            $table->timestamps();
            
            $table->foreign('winner_id')->references('id')->on('users')->nullOnDelete();
        });
        
        Schema::create('lottery_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lottery_id');
            $table->unsignedBigInteger('user_id');
            $table->json('numbers'); // user's picked numbers
            $table->integer('cost')->default(10); // credits spent
            $table->integer('matches')->default(0); // filled after draw
            $table->integer('prize')->default(0); // filled after draw
            $table->timestamps();
            
            $table->foreign('lottery_id')->references('id')->on('daily_lottery')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lottery_tickets');
        Schema::dropIfExists('daily_lottery');
    }
};
