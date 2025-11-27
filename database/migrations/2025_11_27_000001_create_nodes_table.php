<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->integer('node_number')->unique(); // 1-6
            $table->enum('status', ['ONLINE', 'OFFLINE', 'BUSY', 'MAINTENANCE'])->default('ONLINE');
            $table->unsignedBigInteger('current_user_id')->nullable();
            $table->string('current_activity')->nullable(); // "Reading IT-kaos"
            $table->timestamp('user_connected_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->foreign('current_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
