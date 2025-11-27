<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique(); // it-kaos, juss, etc
            $table->string('name_en'); // English name
            $table->string('name_no'); // Norwegian name
            $table->text('description_en')->nullable();
            $table->text('description_no')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('message_count')->default(0);
            $table->integer('story_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
