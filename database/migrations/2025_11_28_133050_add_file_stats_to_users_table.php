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
        Schema::table('users', function (Blueprint $table) {
            // File transfer statistics - add counts first, then bytes
            if (!Schema::hasColumn('users', 'total_uploads')) {
                $table->integer('total_uploads')->unsigned()->default(0);
            }
            if (!Schema::hasColumn('users', 'total_downloads')) {
                $table->integer('total_downloads')->unsigned()->default(0);
            }
        });
        
        // Separate call to ensure columns exist before referencing them
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'upload_bytes')) {
                $table->bigInteger('upload_bytes')->unsigned()->default(0);
            }
            if (!Schema::hasColumn('users', 'download_bytes')) {
                $table->bigInteger('download_bytes')->unsigned()->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['upload_bytes', 'download_bytes']);
        });
    }
};
