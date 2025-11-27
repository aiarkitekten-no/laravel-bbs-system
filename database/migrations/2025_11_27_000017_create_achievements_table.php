<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name_en');
            $table->string('name_no');
            $table->text('description_en');
            $table->text('description_no');
            $table->string('icon')->nullable(); // ASCII art or emoji
            $table->integer('points')->default(0);
            $table->enum('category', ['MESSAGES', 'FILES', 'GAMES', 'SOCIAL', 'TIME', 'SPECIAL']);
            $table->json('requirements')->nullable(); // conditions to earn
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
