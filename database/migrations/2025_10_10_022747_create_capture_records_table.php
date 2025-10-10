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
        Schema::create('capture_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fish_id');
            $table->string('image_path', 500);
            $table->enum('tribe', ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']);
            $table->string('location', 255);
            $table->string('capture_method', 255);
            $table->date('capture_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fish_id')->references('id')->on('fish')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capture_records');
    }
};
