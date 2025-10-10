<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tribal_classifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fish_id');
            $table->enum('tribe', ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']);
            $table->enum('food_category', ['oyod', 'rahet', '不分類', '不食用', '?', ''])->default('');
            $table->enum('processing_method', ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''])->default('');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fish_id')->references('id')->on('fish')->onDelete('cascade');
            $table->unique(['fish_id', 'tribe'], 'unique_fish_tribe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tribal_classifications');
    }
};
