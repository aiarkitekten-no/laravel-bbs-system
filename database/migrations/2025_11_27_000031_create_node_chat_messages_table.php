<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_node_id');
            $table->unsignedBigInteger('to_node_id')->nullable(); // null = broadcast to all
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_page')->default(false); // true = SysOp page
            $table->timestamps();
            
            $table->foreign('from_node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('to_node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('from_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['to_node_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_chat_messages');
    }
};
