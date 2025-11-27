<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 50)->unique(); // BBS handle/username
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('level', ['GUEST', 'USER', 'ELITE', 'COSYSOP', 'SYSOP'])->default('USER');
            $table->string('locale', 5)->default('en'); // en or no
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->text('ascii_signature')->nullable();
            $table->date('birthday')->nullable();
            $table->integer('total_logins')->default(0);
            $table->integer('total_messages')->default(0);
            $table->integer('total_files_uploaded')->default(0);
            $table->integer('total_files_downloaded')->default(0);
            $table->bigInteger('total_time_online')->default(0); // seconds
            $table->integer('credits')->default(100); // BBS credits
            $table->integer('daily_time_used')->default(0); // seconds used today
            $table->integer('daily_time_limit')->default(3600); // 60 min default
            $table->integer('time_bank')->default(0); // saved time
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->string('bot_personality')->nullable(); // for bot users
            $table->boolean('is_online')->default(false);
            $table->unsignedBigInteger('current_node_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('level');
            $table->index('is_online');
            $table->index('is_bot');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
