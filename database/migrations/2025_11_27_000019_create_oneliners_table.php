<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oneliners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('content', 255);
            $table->boolean('is_ai_generated')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oneliners');
    }
};
