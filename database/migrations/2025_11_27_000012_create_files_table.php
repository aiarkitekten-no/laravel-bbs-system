<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('uploader_id');
            $table->string('filename'); // original filename
            $table->string('storage_path'); // path on disk
            $table->string('file_id_diz')->nullable(); // FILE_ID.DIZ content
            $table->text('description')->nullable();
            $table->bigInteger('file_size'); // bytes
            $table->string('mime_type')->nullable();
            $table->string('md5_hash', 32)->nullable();
            $table->integer('download_count')->default(0);
            $table->integer('credits_cost')->default(0); // cost to download
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'QUARANTINED'])->default('PENDING');
            $table->boolean('virus_scanned')->default(false);
            $table->timestamp('virus_scanned_at')->nullable();
            $table->string('virus_scan_result')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('category_id')->references('id')->on('file_categories')->cascadeOnDelete();
            $table->foreign('uploader_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('md5_hash'); // for duplicate checking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
