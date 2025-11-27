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
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('group_name')->nullable();
            $table->mediumText('content');
            $table->unsignedInteger('width')->default(80);
            $table->unsignedInteger('height')->default(25);
            $table->string('category')->default('artwork');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_featured']);
            $table->index('view_count');
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('description')->nullable();
            $table->json('options');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_multiple_choice')->default(false);
            $table->boolean('show_results_before_vote')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('total_votes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option_index');
            $table->timestamps();

            $table->unique(['poll_id', 'user_id', 'option_index']);
        });

        Schema::create('bbs_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('telnet_address')->nullable();
            $table->string('sysop_name')->nullable();
            $table->string('location')->nullable();
            $table->string('software')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_online')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_featured']);
        });

        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'priority', 'starts_at', 'expires_at']);
        });

        Schema::create('logoff_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('quote');
            $table->string('author')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->unsignedInteger('times_shown')->default(0);
            $table->timestamps();

            $table->index('is_approved');
        });

        Schema::create('time_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('saved_minutes')->default(0);
            $table->unsignedInteger('max_save_minutes')->default(120);
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('founder_id')->constrained('users')->cascadeOnDelete();
            $table->text('logo_ansi')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('max_members')->default(100);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_public', 'is_active']);
        });

        Schema::create('user_club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['user_club_id', 'user_id']);
        });

        Schema::create('user_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('award_type');
            $table->date('award_month');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->string('badge_icon')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'award_type']);
            $table->index('award_month');
        });

        Schema::create('graffiti_wall', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content', 80);
            $table->string('color')->default('white');
            $table->unsignedSmallInteger('position_x')->default(0);
            $table->unsignedSmallInteger('position_y')->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graffiti_wall');
        Schema::dropIfExists('user_awards');
        Schema::dropIfExists('user_club_members');
        Schema::dropIfExists('user_clubs');
        Schema::dropIfExists('time_bank');
        Schema::dropIfExists('logoff_quotes');
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('bbs_links');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('ansi_art');
    }
};
