<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('reply_to_id')->nullable(); // for quoting
            $table->text('body');
            $table->text('body_html')->nullable(); // rendered version
            $table->boolean('is_bot_generated')->default(false);
            $table->string('bot_personality')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('thread_id')->references('id')->on('message_threads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reply_to_id')->references('id')->on('messages')->nullOnDelete();
            $table->index('created_at');
        });
        
        // Add foreign key for last_message_id in threads
        Schema::table('message_threads', function (Blueprint $table) {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('message_threads', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });
        Schema::dropIfExists('messages');
    }
};
