<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track which threads/messages user has read
        Schema::create('user_read_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('thread_id')->references('id')->on('message_threads')->cascadeOnDelete();
            $table->foreign('last_read_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->unique(['user_id', 'thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_read_status');
    }
};
