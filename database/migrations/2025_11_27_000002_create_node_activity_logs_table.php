<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('node_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('action', ['LOGIN', 'LOGOUT', 'ACTIVITY', 'TIMEOUT', 'DISCONNECT']);
            $table->string('activity_description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_activity_logs');
    }
};
