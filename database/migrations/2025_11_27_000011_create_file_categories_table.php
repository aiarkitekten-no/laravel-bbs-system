<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_no');
            $table->text('description_en')->nullable();
            $table->text('description_no')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('file_count')->default(0);
            $table->bigInteger('total_size')->default(0); // bytes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_categories');
    }
};
