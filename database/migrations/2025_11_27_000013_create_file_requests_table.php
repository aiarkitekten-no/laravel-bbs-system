<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('filename_requested');
            $table->text('description')->nullable();
            $table->enum('status', ['OPEN', 'FULFILLED', 'CLOSED'])->default('OPEN');
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->unsignedBigInteger('fulfilled_file_id')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('fulfilled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('fulfilled_file_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_requests');
    }
};
