<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('island_characters', function (Blueprint $table): void {
            $table->id();
            // 一個帳號一個角色
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->string('tribe', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('island_characters');
    }
};
