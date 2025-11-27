<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ansi_art', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // null = system art
            $table->string('title');
            $table->string('artist')->nullable();
            $table->text('content'); // the ANSI art itself
            $table->enum('type', ['LOGON', 'LOGOFF', 'MENU', 'GALLERY', 'BANNER']);
            $table->integer('width')->default(80);
            $table->integer('height')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('view_count')->default(0);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ansi_art');
    }
};
