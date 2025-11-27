<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_awards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('award_type'); // USER_OF_MONTH, TOP_POSTER, TOP_UPLOADER, etc
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('award_month'); // the month this award is for
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'award_type', 'award_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_awards');
    }
};
