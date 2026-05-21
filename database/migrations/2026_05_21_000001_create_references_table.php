<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('references', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->string('external_url')->nullable();
            $table->string('author');
            $table->string('status', 20)->default('enabled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('references');
    }
};

