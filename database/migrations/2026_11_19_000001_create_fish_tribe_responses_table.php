<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fish_tribe_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fish_id')->constrained()->cascadeOnDelete();
            $table->string('tribe', 32);
            $table->string('name', 60)->nullable();
            $table->string('food_category', 32)->nullable();
            // 目前這個叫法被送出的時間（微秒）；改成不同寫法才會更新，用來判斷「最早送出」
            $table->timestamp('name_submitted_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fish_id', 'tribe'], 'fish_tribe_responses_user_fish_tribe_unique');
            $table->index('fish_id', 'fish_tribe_responses_fish_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fish_tribe_responses');
    }
};
