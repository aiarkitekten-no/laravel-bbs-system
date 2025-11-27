<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('recipient_id');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('sender_deleted')->default(false);
            $table->boolean('recipient_deleted')->default(false);
            $table->timestamps();
            
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['recipient_id', 'is_read']);
            $table->index(['sender_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_messages');
    }
};
