<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tag', 10)->unique(); // short tag like [ELITE]
            $table->text('description')->nullable();
            $table->unsignedBigInteger('founder_id');
            $table->integer('member_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_invite_only')->default(false);
            $table->timestamps();
            
            $table->foreign('founder_id')->references('id')->on('users')->cascadeOnDelete();
        });
        
        Schema::create('user_club_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['MEMBER', 'OFFICER', 'LEADER'])->default('MEMBER');
            $table->timestamp('joined_at');
            $table->timestamps();
            
            $table->foreign('club_id')->references('id')->on('user_clubs')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['club_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_club_members');
        Schema::dropIfExists('user_clubs');
    }
};
