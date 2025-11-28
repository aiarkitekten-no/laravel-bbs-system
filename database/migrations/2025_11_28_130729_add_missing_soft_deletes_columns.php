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
        // Add deleted_at to ansi_art if it doesn't exist
        if (!Schema::hasColumn('ansi_art', 'deleted_at')) {
            Schema::table('ansi_art', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add missing columns to user_clubs if they don't exist
        if (Schema::hasTable('user_clubs')) {
            Schema::table('user_clubs', function (Blueprint $table) {
                if (!Schema::hasColumn('user_clubs', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('user_clubs', 'is_public')) {
                    $table->boolean('is_public')->default(true)->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ansi_art', 'deleted_at')) {
            Schema::table('ansi_art', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('user_clubs')) {
            if (Schema::hasColumn('user_clubs', 'deleted_at')) {
                Schema::table('user_clubs', function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
            if (Schema::hasColumn('user_clubs', 'is_public')) {
                Schema::table('user_clubs', function (Blueprint $table) {
                    $table->dropColumn('is_public');
                });
            }
        }
    }
};
