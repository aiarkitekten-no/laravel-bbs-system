<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trivia_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable(); // links to BBS category or null for general
            $table->text('question');
            $table->string('correct_answer');
            $table->json('wrong_answers'); // array of 3 wrong answers
            $table->enum('difficulty', ['EASY', 'MEDIUM', 'HARD'])->default('MEDIUM');
            $table->integer('points')->default(10);
            $table->integer('times_asked')->default(0);
            $table->integer('times_correct')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trivia_questions');
    }
};
