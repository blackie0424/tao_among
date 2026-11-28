<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** #232 魚島圖鑑：角色在海邊收集過的魚。記錄內容仍在 fish_tribe_responses，這裡只記「收集過、什麼時候」。 */
    public function up(): void
    {
        Schema::create('island_collected_fish', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('island_character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fish_id')->constrained('fish')->cascadeOnDelete();
            $table->timestamp('collected_at');
            $table->timestamps();
            // 同一條魚只收一次
            $table->unique(['island_character_id', 'fish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('island_collected_fish');
    }
};
